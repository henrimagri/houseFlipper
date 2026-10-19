<?php

namespace App\Services\RealEstateData;

use App\Models\ItbiImportBatch;
use App\Models\RealEstateTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ItbiTransactionImportService
{
    public function __construct(
        private readonly ItbiSpreadsheetParserService $parser,
        private readonly ItbiTransactionNormalizerService $normalizer,
    ) {}

    public function import(ItbiImportBatch $batch, ?int $limit = null): ItbiImportBatch
    {
        if (! $batch->storage_path) {
            throw new \RuntimeException('Lote sem arquivo baixado.');
        }

        $claimed = ItbiImportBatch::query()
            ->whereKey($batch->id)
            ->where('status', '!=', 'processing')
            ->update([
                'status' => 'processing',
                'processing_started_at' => now(),
                'processing_finished_at' => null,
                'error_message' => null,
                'total_rows' => 0,
                'imported_rows' => 0,
                'updated_rows' => 0,
                'skipped_rows' => 0,
                'failed_rows' => 0,
            ]);
        if ($claimed !== 1) {
            throw new \RuntimeException('Este lote já está sendo processado.');
        }
        $batch->refresh();

        $stats = [
            'total_rows' => 0,
            'imported_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
        ];

        try {
            foreach ($this->parser->rows($batch->storage_path) as $index => $row) {
                if ($limit && $stats['total_rows'] >= $limit) {
                    break;
                }

                $stats['total_rows']++;

                try {
                    $data = $this->normalizer->normalize($row, $index + 2, config('real_estate_data.itbi_sp.source_name'));
                    $data['import_batch_id'] = $batch->id;

                    if (! $data['transaction_date'] && ! $data['declared_transaction_value'] && ! $data['tax_base_value']) {
                        $stats['skipped_rows']++;

                        continue;
                    }

                    DB::transaction(function () use ($data, &$stats) {
                        $transaction = RealEstateTransaction::query()->where([
                            'source_type' => $data['source_type'],
                            'source_name' => $data['source_name'],
                            'source_reference' => $data['source_reference'],
                        ])->lockForUpdate()->first();

                        if ($transaction) {
                            $transaction->update($data);
                            $stats['updated_rows']++;

                            return;
                        }

                        RealEstateTransaction::create($data);
                        $stats['imported_rows']++;
                    });
                } catch (\Throwable $exception) {
                    $stats['failed_rows']++;
                    Log::warning('Falha ao importar linha ITBI', [
                        'batch_id' => $batch->id,
                        'row' => $index + 2,
                        'exception_type' => $exception::class,
                    ]);
                }
            }

            if ($stats['imported_rows'] + $stats['updated_rows'] === 0) {
                throw new \RuntimeException('Nenhuma transação válida foi encontrada no arquivo.');
            }

            $batch->update([
                ...$stats,
                'status' => $stats['failed_rows'] > 0 ? 'completed_with_errors' : 'completed',
                'processing_finished_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $publicMessage = 'Falha ao processar o lote ITBI.';

            $batch->update([
                ...$stats,
                'status' => 'failed',
                'error_message' => $publicMessage,
                'processing_finished_at' => now(),
            ]);

            Log::error('Falha ao processar lote ITBI', [
                'batch_id' => $batch->id,
                'exception_type' => $exception::class,
            ]);

            throw new \RuntimeException($publicMessage, 0, $exception);
        }

        return $batch->refresh();
    }
}
