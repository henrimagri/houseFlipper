<?php

namespace App\Services\RealEstateData;

use App\Models\ItbiImportBatch;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ItbiFileDownloadService
{
    public function __construct(private readonly ItbiRemoteFileClient $client) {}

    public function download(ItbiImportBatch $batch): ItbiImportBatch
    {
        try {
            return $this->downloadAndStore($batch);
        } catch (\Throwable $exception) {
            $message = 'Falha ao baixar ou armazenar arquivo ITBI.';
            $batch->refresh();
            $batch->update(['status' => 'failed', 'error_message' => $message]);
            throw new \RuntimeException($message, 0, $exception);
        }
    }

    private function downloadAndStore(ItbiImportBatch $batch): ItbiImportBatch
    {
        $response = $this->client->get($batch->source_url);

        if (! $response->successful()) {
            throw new \RuntimeException('Download falhou: HTTP '.$response->status());
        }

        $contents = $response->body();
        $hash = hash('sha256', $contents);

        if ($batch->file_hash === $hash && $batch->storage_path && Storage::disk($this->disk())->exists($batch->storage_path)) {
            return $batch;
        }

        $extension = Str::lower(pathinfo($batch->source_filename, PATHINFO_EXTENSION)) ?: 'bin';
        $safeName = Str::slug(pathinfo($batch->source_filename, PATHINFO_FILENAME)) ?: 'itbi-sp';
        $path = trim(config('real_estate_data.itbi_sp.storage_path'), '/').'/'.$safeName.'-'.$hash.'.'.$extension;
        $disk = Storage::disk($this->disk());
        $alreadyExists = $disk->exists($path);

        if (! $alreadyExists && ! $disk->put($path, $contents)) {
            throw new \RuntimeException('Não foi possível armazenar o arquivo ITBI.');
        }

        try {
            if (! $batch->update([
                'file_hash' => $hash,
                'storage_path' => $path,
                'status' => 'downloaded',
                'downloaded_at' => now(),
                'error_message' => null,
            ])) {
                throw new \RuntimeException('Não foi possível atualizar o lote ITBI.');
            }
        } catch (\Throwable $exception) {
            if (! $alreadyExists) {
                try {
                    if (! $disk->delete($path)) {
                        throw new \RuntimeException('O arquivo temporário não pôde ser removido.');
                    }
                } catch (\Throwable $cleanupException) {
                    Log::warning('Falha ao remover download ITBI após erro de persistência', [
                        'path_hash' => hash('sha256', $path),
                        'exception_type' => $cleanupException::class,
                    ]);
                }
            }

            throw $exception;
        }

        return $batch->refresh();
    }

    private function disk(): string
    {
        return config('real_estate_data.itbi_sp.storage_disk', 'local');
    }
}
