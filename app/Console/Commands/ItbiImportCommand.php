<?php

namespace App\Console\Commands;

use App\Models\ItbiImportBatch;
use App\Services\RealEstateData\ItbiFileDownloadService;
use App\Services\RealEstateData\ItbiTransactionImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ItbiImportCommand extends Command
{
    protected $signature = 'itbi:import {--batch=} {--limit=} {--recover-stale= : Recupera lotes em processamento há pelo menos N minutos}';

    protected $description = 'Baixa e importa lotes de ITBI descobertos.';

    public function handle(ItbiFileDownloadService $download, ItbiTransactionImportService $importer): int
    {
        $batchId = $this->option('batch');
        $limit = $this->option('limit');
        $recoverStale = $this->option('recover-stale');
        foreach (['batch' => $batchId, 'limit' => $limit, 'recover-stale' => $recoverStale] as $name => $value) {
            if ($value !== null && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $this->error("A opção --{$name} deve ser um inteiro positivo.");

                return self::FAILURE;
            }
        }

        $staleBefore = $recoverStale !== null ? now()->subMinutes((int) $recoverStale) : null;
        $query = ItbiImportBatch::query()->where(function ($statuses) use ($staleBefore): void {
            $statuses->whereIn('status', ['discovered', 'downloaded', 'failed', 'completed_with_errors']);
            if ($staleBefore !== null) {
                $statuses->orWhere(function ($processing) use ($staleBefore): void {
                    $processing->where('status', 'processing')
                        ->where('processing_started_at', '<=', $staleBefore);
                });
            }
        });

        if ($batchId !== null) {
            $query->whereKey($batchId);
        }

        $batches = $query->orderBy('id')->get();
        if ($batches->isEmpty()) {
            $this->info('Nenhum lote pendente para importar.');

            return self::SUCCESS;
        }

        $limit = $limit !== null ? (int) $limit : null;
        $failed = false;

        foreach ($batches as $batch) {
            $this->line("Processando lote #{$batch->id}: {$batch->source_filename}");

            try {
                if ($batch->status === 'processing') {
                    $released = ItbiImportBatch::query()
                        ->whereKey($batch->id)
                        ->where('status', 'processing')
                        ->where('processing_started_at', '<=', $staleBefore)
                        ->update([
                            'status' => 'failed',
                            'error_message' => 'Processamento anterior liberado explicitamente para recuperação.',
                            'processing_finished_at' => now(),
                        ]);
                    if ($released !== 1) {
                        $this->warn("Lote #{$batch->id} não está mais elegível para recuperação.");

                        continue;
                    }
                    $batch->refresh();
                }

                if (! $batch->storage_path || ! Storage::disk(config('real_estate_data.itbi_sp.storage_disk', 'local'))->exists($batch->storage_path)) {
                    $batch = $download->download($batch);
                }

                $batch = $importer->import($batch, $limit);

                $this->info("Status: {$batch->status} | linhas={$batch->total_rows} importadas={$batch->imported_rows} atualizadas={$batch->updated_rows} ignoradas={$batch->skipped_rows} falhas={$batch->failed_rows}");
                $failed = $failed || $batch->status !== 'completed';
            } catch (\Throwable $exception) {
                $failed = true;
                $this->error("Falha no lote #{$batch->id}. Consulte os logs usando o identificador do lote.");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
