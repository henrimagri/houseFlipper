<?php

namespace App\Services\RealEstateData;

use App\Models\ItbiImportBatch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ItbiFileDownloadService
{
    public function download(ItbiImportBatch $batch): ItbiImportBatch
    {
        $response = Http::timeout(120)
            ->accept('*/*')
            ->withUserAgent('InvestFlip/1.0 (+itbi-sp-download)')
            ->get($batch->source_url);

        if (! $response->successful()) {
            $batch->update(['status' => 'failed', 'error_message' => 'Download falhou: HTTP '.$response->status()]);
            throw new \RuntimeException($batch->error_message);
        }

        $contents = $response->body();
        $hash = hash('sha256', $contents);

        if ($batch->file_hash === $hash && $batch->storage_path && Storage::disk($this->disk())->exists($batch->storage_path)) {
            return $batch;
        }

        $extension = Str::lower(pathinfo($batch->source_filename, PATHINFO_EXTENSION)) ?: 'bin';
        $safeName = Str::slug(pathinfo($batch->source_filename, PATHINFO_FILENAME)) ?: 'itbi-sp';
        $path = trim(config('real_estate_data.itbi_sp.storage_path'), '/').'/'.$safeName.'-'.$hash.'.'.$extension;

        Storage::disk($this->disk())->put($path, $contents);

        $batch->update([
            'file_hash' => $hash,
            'storage_path' => $path,
            'status' => 'downloaded',
            'downloaded_at' => now(),
            'error_message' => null,
        ]);

        return $batch->refresh();
    }

    private function disk(): string
    {
        return config('real_estate_data.itbi_sp.storage_disk', 'local');
    }
}
