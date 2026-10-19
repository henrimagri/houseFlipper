<?php

namespace App\Services;

use App\Enums\OpportunityStatus;
use App\Exceptions\OpportunityPhotoStorageException;
use App\Models\Opportunity;
use App\Models\User;
use App\Support\ExternalImageUrlPolicy;
use App\Support\OpportunityPhotoPathPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpportunityService
{
    private const FILE_KEYS = ['photos', 'cover_photo', 'existing_cover_photo_id', 'cover_image_url'];

    public function create(User $user, array $data): Opportunity
    {
        return $this->withPhotoTransaction(function (array &$uploadedPaths) use ($user, $data) {
            $opportunity = $user->opportunities()->create(Arr::except($data, self::FILE_KEYS));
            $this->validatePhotoLimit($opportunity, $data);
            $this->storePhotos($opportunity, $data['photos'] ?? [], $data['cover_photo'] ?? null, $uploadedPaths);
            $this->storeExternalCoverPhoto($opportunity, $data['cover_image_url'] ?? null);
            $this->recordStatus($opportunity, $user, null, $opportunity->status);

            return $opportunity->load('photos');
        });
    }

    public function update(Opportunity $opportunity, User $user, array $data): Opportunity
    {
        return $this->withPhotoTransaction(function (array &$uploadedPaths) use ($opportunity, $user, $data) {
            $opportunity = Opportunity::lockForUpdate()->findOrFail($opportunity->id);
            $this->validatePhotoLimit($opportunity, $data);
            $oldStatus = $opportunity->status;
            $opportunity->update(Arr::except($data, self::FILE_KEYS));
            if (isset($data['existing_cover_photo_id'])) {
                $cover = $opportunity->photos()->findOrFail($data['existing_cover_photo_id']);
                $opportunity->photos()->update(['is_cover' => false]);
                $cover->update(['is_cover' => true]);
            }
            $this->storePhotos($opportunity, $data['photos'] ?? [], $data['cover_photo'] ?? null, $uploadedPaths);
            $this->storeExternalCoverPhoto($opportunity, $data['cover_image_url'] ?? null);
            if ($oldStatus !== $opportunity->status) {
                $this->recordStatus($opportunity, $user, $oldStatus, $opportunity->status);
            }

            return $opportunity->refresh();
        });
    }

    public function delete(Opportunity $opportunity): void
    {
        $quarantined = [];

        try {
            DB::transaction(function () use ($opportunity, &$quarantined): void {
                $locked = Opportunity::with('photos')->lockForUpdate()->findOrFail($opportunity->id);

                foreach ($locked->photos as $photo) {
                    $path = (string) $photo->path;
                    if ($this->isExternalPhoto($path) || ! Storage::disk($this->photoDisk())->exists($path)) {
                        continue;
                    }

                    if (! OpportunityPhotoPathPolicy::allows($path)) {
                        throw new OpportunityPhotoStorageException('Caminho de foto local inválido.');
                    }

                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $quarantinePath = 'opportunity-photo-quarantine/'.Str::uuid().($extension ? '.'.$extension : '');
                    $this->copyFile($this->photoDisk(), $path, 'local', $quarantinePath);
                    $quarantined[$path] = $quarantinePath;

                    if (! Storage::disk($this->photoDisk())->delete($path)) {
                        throw new OpportunityPhotoStorageException('Não foi possível retirar a foto do armazenamento público.');
                    }
                }

                $locked->photos()->delete();
                $locked->delete();
            });
        } catch (\Throwable $exception) {
            $this->restoreQuarantinedPhotos($quarantined, $opportunity->id);
            throw $exception;
        }

        foreach ($quarantined as $quarantinePath) {
            if (! Storage::disk('local')->delete($quarantinePath)) {
                Log::warning('Foto de oportunidade permaneceu na quarentena privada após exclusão', [
                    'opportunity_id' => $opportunity->id,
                ]);
            }
        }
    }

    public function changeStatus(Opportunity $opportunity, User $user, OpportunityStatus $status, ?string $notes = null): void
    {
        DB::transaction(function () use ($opportunity, $user, $status, $notes) {
            $oldStatus = $opportunity->status;
            if ($oldStatus === $status) {
                return;
            }
            $opportunity->update(['status' => $status, 'is_archived' => $status === OpportunityStatus::Archived]);
            $this->recordStatus($opportunity, $user, $oldStatus, $status, $notes);
        });
    }

    public function archive(Opportunity $opportunity, User $user): void
    {
        $this->changeStatus($opportunity, $user, OpportunityStatus::Archived);
    }

    public function discard(Opportunity $opportunity, User $user, string $reason): void
    {
        DB::transaction(function () use ($opportunity, $user, $reason) {
            $old = $opportunity->status;
            $opportunity->update(['status' => OpportunityStatus::Discarded, 'discarded_reason' => $reason]);
            if ($old !== OpportunityStatus::Discarded) {
                $this->recordStatus($opportunity, $user, $old, OpportunityStatus::Discarded, $reason);
            }
        });
    }

    private function withPhotoTransaction(callable $callback): Opportunity
    {
        $uploadedPaths = [];
        try {
            return DB::transaction(function () use ($callback, &$uploadedPaths) {
                return $callback($uploadedPaths);
            });
        } catch (\Throwable $exception) {
            if ($uploadedPaths !== []) {
                try {
                    if (! Storage::disk($this->photoDisk())->delete($uploadedPaths)) {
                        throw new \RuntimeException('Photo cleanup failed');
                    }
                } catch (\Throwable) {
                    Log::error('Falha na limpeza de fotos após rollback de oportunidade', ['new_file_count' => count($uploadedPaths)]);
                }
            }
            throw $exception;
        }
    }

    private function restoreQuarantinedPhotos(array $quarantined, int $opportunityId): void
    {
        foreach ($quarantined as $originalPath => $quarantinePath) {
            try {
                if (! Storage::disk($this->photoDisk())->exists($originalPath)) {
                    $this->copyFile('local', $quarantinePath, $this->photoDisk(), $originalPath);
                }
                Storage::disk('local')->delete($quarantinePath);
            } catch (\Throwable) {
                Log::critical('Falha ao restaurar foto após rollback de exclusão de oportunidade', [
                    'opportunity_id' => $opportunityId,
                ]);
            }
        }
    }

    private function copyFile(string $sourceDisk, string $sourcePath, string $destinationDisk, string $destinationPath): void
    {
        $stream = Storage::disk($sourceDisk)->readStream($sourcePath);
        if (! is_resource($stream)) {
            throw new OpportunityPhotoStorageException('Não foi possível ler a foto para movimentação segura.');
        }

        try {
            $stored = Storage::disk($destinationDisk)->put($destinationPath, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new OpportunityPhotoStorageException('Não foi possível gravar a foto na quarentena segura.');
        }
    }

    private function photoDisk(): string
    {
        return (string) config('opportunities.photo_disk', 'public');
    }

    private function isExternalPhoto(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    private function validatePhotoLimit(Opportunity $opportunity, array $data): void
    {
        $newCount = count($data['photos'] ?? []);
        if (! empty($data['cover_image_url']) && ! $opportunity->photos()->where('path', $data['cover_image_url'])->exists()) {
            $newCount++;
        }
        if ($newCount > 0 && $opportunity->photos()->count() + $newCount > 20) {
            throw ValidationException::withMessages(['photos' => 'A oportunidade pode ter no máximo 20 fotos, incluindo as já salvas.']);
        }
    }

    private function storePhotos(Opportunity $opportunity, array $photos, ?int $cover, array &$uploadedPaths): void
    {
        $offset = (int) $opportunity->photos()->max('order') + 1;
        foreach ($photos as $index => $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }
            $isCover = $cover !== null ? $cover === $index : $opportunity->photos()->count() === 0 && $index === 0;
            if ($isCover) {
                $opportunity->photos()->update(['is_cover' => false]);
            }
            $path = $photo->store('opportunities', $this->photoDisk());
            if (! $path) {
                throw new \RuntimeException('Não foi possível armazenar a foto.');
            }
            $uploadedPaths[] = $path;
            $opportunity->photos()->create(['path' => $path, 'original_name' => $this->safeOriginalName($photo->getClientOriginalName()), 'order' => $offset + $index, 'is_cover' => $isCover]);
        }
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '_', $name) ?: 'foto';

        return Str::limit($name, 240, '');
    }

    private function storeExternalCoverPhoto(Opportunity $opportunity, ?string $url): void
    {
        if (! $url) {
            return;
        }

        if (! ExternalImageUrlPolicy::allows($url)) {
            throw ValidationException::withMessages([
                'cover_image_url' => 'A imagem de capa deve usar uma URL HTTPS pública e sem credenciais.',
            ]);
        }

        if ($opportunity->photos()->where('path', $url)->exists()) {
            return;
        }

        $opportunity->photos()->update(['is_cover' => false]);
        $opportunity->photos()->create([
            'path' => $url,
            'original_name' => 'Foto do anúncio',
            'order' => (int) $opportunity->photos()->max('order') + 1,
            'is_cover' => true,
        ]);
    }

    private function recordStatus(Opportunity $opportunity, User $user, ?OpportunityStatus $old, OpportunityStatus $new, ?string $notes = null): void
    {
        $opportunity->statusHistories()->create(['user_id' => $user->id, 'old_status' => $old, 'new_status' => $new, 'notes' => $notes]);
    }
}
