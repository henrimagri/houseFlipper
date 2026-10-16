<?php

namespace App\Services;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OpportunityService
{
    private const FILE_KEYS = ['photos', 'cover_photo', 'existing_cover_photo_id', 'cover_image_url'];

    public function create(User $user, array $data): Opportunity
    {
        return DB::transaction(function () use ($user, $data) {
            $opportunity = $user->opportunities()->create(Arr::except($data, self::FILE_KEYS));
            $this->storePhotos($opportunity, $data['photos'] ?? [], $data['cover_photo'] ?? null);
            $this->storeExternalCoverPhoto($opportunity, $data['cover_image_url'] ?? null);
            $this->recordStatus($opportunity, $user, null, $opportunity->status);

            return $opportunity->load('photos');
        });
    }

    public function update(Opportunity $opportunity, User $user, array $data): Opportunity
    {
        return DB::transaction(function () use ($opportunity, $user, $data) {
            $oldStatus = $opportunity->status;
            $opportunity->update(Arr::except($data, self::FILE_KEYS));
            if (isset($data['existing_cover_photo_id'])) {
                $cover = $opportunity->photos()->findOrFail($data['existing_cover_photo_id']);
                $opportunity->photos()->update(['is_cover' => false]);
                $cover->update(['is_cover' => true]);
            }
            $this->storePhotos($opportunity, $data['photos'] ?? [], $data['cover_photo'] ?? null);
            $this->storeExternalCoverPhoto($opportunity, $data['cover_image_url'] ?? null);
            if ($oldStatus !== $opportunity->status) {
                $this->recordStatus($opportunity, $user, $oldStatus, $opportunity->status);
            }

            return $opportunity->refresh();
        });
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

    private function storePhotos(Opportunity $opportunity, array $photos, ?int $cover): void
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
            $opportunity->photos()->create(['path' => $photo->store('opportunities', 'public'), 'original_name' => $photo->getClientOriginalName(), 'order' => $offset + $index, 'is_cover' => $isCover]);
        }
    }

    private function storeExternalCoverPhoto(Opportunity $opportunity, ?string $url): void
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
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
