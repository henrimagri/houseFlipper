<?php

namespace App\Services;

use App\Exceptions\DocumentStorageException;
use App\Models\Documento;
use App\Models\Projeto;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentoService
{
    public function __construct(private readonly DocumentMalwareScanner $malwareScanner) {}

    public function listar(array $filtros = [])
    {
        $query = Documento::query()->ativos()->with(['projeto', 'enviadoPor']);
        if (! empty($filtros['busca'])) {
            $busca = $filtros['busca'];
            $query->where(function ($builder) use ($busca): void {
                $builder->where('nome_original', 'like', '%'.$busca.'%')
                    ->orWhere('descricao', 'like', '%'.$busca.'%');
            });
        }
        foreach (['projeto_id', 'categoria', 'status'] as $filter) {
            if (isset($filtros[$filter]) && $filtros[$filter] !== '') {
                $query->where($filter, $filtros[$filter]);
            }
        }

        return $query->orderByDesc('data_inclusao')->orderByDesc('id')->paginate($filtros['per_page'] ?? 25);
    }

    public function obter(int $id): ?Documento
    {
        return Documento::ativos()->with(['projeto', 'enviadoPor'])->find($id);
    }

    public function criar(array $dados, UploadedFile $arquivo, User $user): Documento
    {
        $this->malwareScanner->assertClean($arquivo);
        $disk = config('documents.disk');
        $extension = strtolower($arquivo->getClientOriginalExtension());
        $path = 'documents/'.$user->tenant_id.'/'.Str::uuid().'.'.$extension;
        try {
            $stored = Storage::disk($disk)->putFileAs(dirname($path), $arquivo, basename($path));
        } catch (\Throwable $exception) {
            throw new DocumentStorageException('Não foi possível armazenar o documento.', previous: $exception);
        }
        if (! $stored) {
            throw new DocumentStorageException('Não foi possível armazenar o documento.');
        }

        unset($dados['arquivo']);
        try {
            return DB::transaction(function () use ($dados, $arquivo, $path, $user): Documento {
                $this->lockProjects($dados);

                return Documento::create(array_merge($dados, [
                    'enviado_por' => $user->id,
                    'nome_original' => $this->safeOriginalName($arquivo->getClientOriginalName()),
                    'caminho' => $path,
                    'mime_type' => $arquivo->getMimeType() ?: 'application/octet-stream',
                    'tamanho_bytes' => (int) $arquivo->getSize(),
                ]))->load(['projeto', 'enviadoPor']);
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public function atualizar(Documento $documento, array $dados): Documento
    {
        unset($dados['arquivo']);

        return DB::transaction(function () use ($documento, $dados): Documento {
            $this->lockProjects($dados, $documento);
            $documento->update($dados);

            return $documento->fresh(['projeto', 'enviadoPor']);
        });
    }

    public function excluir(Documento $documento): void
    {
        $disk = null;
        $quarantinePath = null;
        try {
            $disk = Storage::disk(config('documents.disk'));
            DB::transaction(function () use ($documento, $disk, &$quarantinePath): void {
                $locked = Documento::query()->lockForUpdate()->findOrFail($documento->id);
                $path = (string) $locked->caminho;
                if (! $this->isManagedPath($path, (int) $locked->tenant_id)) {
                    throw new DocumentStorageException('Caminho de documento inválido.');
                }

                if ($disk->exists($path)) {
                    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $candidatePath = 'document-delete-quarantine/'.$locked->tenant_id.'/'.Str::uuid().($extension ? '.'.$extension : '');
                    $this->copyFile($disk, $path, $candidatePath);
                    $quarantinePath = $candidatePath;
                    if (! $disk->delete($path)) {
                        throw new DocumentStorageException('Não foi possível retirar o documento do armazenamento ativo.');
                    }
                }

                $locked->update(['deletado_flg' => true]);
            });
        } catch (\Throwable $exception) {
            if ($disk && $quarantinePath) {
                $this->restoreQuarantinedFile($disk, $documento->caminho, $quarantinePath, $documento->id);
            }
            if ($exception instanceof DocumentStorageException) {
                throw $exception;
            }

            throw $exception;
        }

        if ($quarantinePath && ! $disk->delete($quarantinePath)) {
            Log::warning('Documento permaneceu na quarentena privada após exclusão', [
                'document_id' => $documento->id,
            ]);
        }
    }

    private function copyFile(FilesystemAdapter $disk, string $source, string $destination): void
    {
        try {
            $stream = $disk->readStream($source);
        } catch (\Throwable $exception) {
            throw new DocumentStorageException('Não foi possível ler o documento para movimentação segura.', previous: $exception);
        }
        if (! is_resource($stream)) {
            throw new DocumentStorageException('Não foi possível ler o documento para movimentação segura.');
        }

        try {
            $stored = $disk->put($destination, $stream);
        } catch (\Throwable $exception) {
            throw new DocumentStorageException('Não foi possível gravar o documento na quarentena segura.', previous: $exception);
        } finally {
            fclose($stream);
        }
        if (! $stored) {
            throw new DocumentStorageException('Não foi possível gravar o documento na quarentena segura.');
        }
    }

    private function restoreQuarantinedFile(FilesystemAdapter $disk, string $originalPath, string $quarantinePath, int $documentId): void
    {
        try {
            if (! $disk->exists($originalPath)) {
                $this->copyFile($disk, $quarantinePath, $originalPath);
            }
            $disk->delete($quarantinePath);
        } catch (\Throwable) {
            Log::critical('Falha ao restaurar documento após rollback de exclusão', [
                'document_id' => $documentId,
            ]);
        }
    }

    private function isManagedPath(string $path, int $tenantId): bool
    {
        return preg_match('#\Adocuments/'.preg_quote((string) $tenantId, '#').'/[A-Za-z0-9][A-Za-z0-9._-]*\z#', $path) === 1;
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '_', $name) ?: 'documento';

        return Str::limit($name, 240, '');
    }

    private function lockProjects(array $data, ?Documento $document = null): void
    {
        $newProjectId = array_key_exists('projeto_id', $data) ? $data['projeto_id'] : $document?->projeto_id;
        $projectIds = array_values(array_unique(array_filter([$document?->projeto_id, $newProjectId])));
        if ($projectIds !== []) {
            Projeto::query()->whereIn('id', $projectIds)->orderBy('id')->lockForUpdate()->get();
        }
        if (filled($newProjectId)) {
            Projeto::ativos()->lockForUpdate()->findOrFail((int) $newProjectId);
        }
    }
}
