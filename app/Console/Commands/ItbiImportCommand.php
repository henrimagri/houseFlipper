<?php

namespace App\Console\Commands;

use App\Models\ItbiImportBatch;
use App\Services\RealEstateData\ItbiFileDownloadService;
use App\Services\RealEstateData\ItbiTransactionImportService;
use Illuminate\Console\Command;

class ItbiImportCommand extends Command
{
    protected $signature = 'itbi:import {--batch=} {--limit=}';

    protected $description = 'Baixa e importa lotes de ITBI descobertos.';

    public function handle(ItbiFileDownloadService $download, ItbiTransactionImportService $importer): int
    {
        $query = ItbiImportBatch::query()
            ->whereIn('status', ['discovered', 'downloaded', 'failed']);

        if ($batchId = $this->option('batch')) {
            $query->whereKey($batchId);
        }

        $batches = $query->orderBy('id')->get();
        if ($batches->isEmpty()) {
            $this->info('Nenhum lote pendente para importar.');

            return self::SUCCESS;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        foreach ($batches as $batch) {
            $this->line("Processando lote #{$batch->id}: {$batch->source_filename}");

            try {
                if (! $batch->storage_path || $batch->status !== 'downloaded') {
                    $batch = $download->download($batch);
                }

                $batch = $importer->import($batch, $limit);

                $this->info("Status: {$batch->status} | linhas={$batch->total_rows} importadas={$batch->imported_rows} atualizadas={$batch->updated_rows} ignoradas={$batch->skipped_rows} falhas={$batch->failed_rows}");
            } catch (\Throwable $exception) {
                $this->error("Falha no lote #{$batch->id}: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
