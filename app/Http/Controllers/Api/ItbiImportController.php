<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItbiImportBatch;
use App\Models\RealEstateTransaction;
use App\Services\RealEstateData\ItbiTransactionImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ItbiImportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->tablesAreReady()) {
            return $this->missingTablesResponse();
        }

        $latestBatch = ItbiImportBatch::query()->latest()->first();
        $transactions = RealEstateTransaction::query()
            ->latest('transaction_date')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 20));

        return response()->json([
            'summary' => [
                'batches' => ItbiImportBatch::count(),
                'transactions' => RealEstateTransaction::count(),
                'latest_batch' => $latestBatch ? $this->batchPayload($latestBatch) : null,
            ],
            'transactions' => $transactions->through(fn (RealEstateTransaction $transaction) => $this->transactionPayload($transaction)),
        ]);
    }

    public function upload(Request $request, ItbiTransactionImportService $importer): JsonResponse
    {
        if (! $this->tablesAreReady()) {
            return $this->missingTablesResponse();
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:256000'],
        ]);

        $file = $data['file'];
        $originalName = $file->getClientOriginalName();
        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        if (! in_array($extension, ['ods', 'csv', 'txt', 'xlsx'], true)) {
            return response()->json([
                'message' => 'Formato não suportado. Envie arquivo .ods, .csv, .txt ou .xlsx.',
            ], 422);
        }

        $contents = file_get_contents($file->getRealPath());
        $hash = hash('sha256', $contents ?: '');
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'upload-itbi';
        $storagePath = trim(config('real_estate_data.itbi_sp.storage_path'), '/').'/upload-'.$safeName.'-'.$hash.'.'.$extension;
        $disk = config('real_estate_data.itbi_sp.storage_disk', 'local');

        Storage::disk($disk)->put($storagePath, $contents);

        $batch = ItbiImportBatch::create([
            'provider' => 'manual_upload_itbi_sp',
            'source_url' => 'upload://'.$originalName,
            'source_filename' => $originalName,
            'reference_period' => $request->string('reference_period')->toString() ?: null,
            'file_hash' => $hash,
            'storage_path' => $storagePath,
            'status' => 'downloaded',
            'discovered_at' => now(),
            'downloaded_at' => now(),
            'metadata_json' => [
                'uploaded_by' => $request->user()?->id,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ],
        ]);

        try {
            $batch = $importer->import($batch);
        } catch (\Throwable $exception) {
            Log::error('Falha ao importar arquivo ITBI enviado', [
                'batch_id' => $batch->id,
                'filename' => $originalName,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Falha ao importar arquivo ITBI: '.$exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Arquivo ITBI importado com sucesso.',
            'batch' => $this->batchPayload($batch),
            'summary' => [
                'transactions' => RealEstateTransaction::count(),
                'imported_rows' => $batch->imported_rows,
                'updated_rows' => $batch->updated_rows,
                'failed_rows' => $batch->failed_rows,
            ],
        ], 201);
    }

    private function tablesAreReady(): bool
    {
        return Schema::hasTable('itbi_import_batches') && Schema::hasTable('real_estate_transactions');
    }

    private function missingTablesResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'As tabelas do importador ITBI ainda não existem. Rode: php artisan migrate',
        ], 503);
    }

    private function batchPayload(ItbiImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'provider' => $batch->provider,
            'source_filename' => $batch->source_filename,
            'reference_period' => $batch->reference_period,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'imported_rows' => $batch->imported_rows,
            'updated_rows' => $batch->updated_rows,
            'skipped_rows' => $batch->skipped_rows,
            'failed_rows' => $batch->failed_rows,
            'error_message' => $batch->error_message,
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }

    private function transactionPayload(RealEstateTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'property_sql' => $transaction->property_sql,
            'transaction_date' => $transaction->transaction_date?->toDateString(),
            'declared_transaction_value' => (float) $transaction->declared_transaction_value,
            'tax_base_value' => (float) $transaction->tax_base_value,
            'property_type' => $transaction->property_type,
            'transaction_nature' => $transaction->transaction_nature,
            'street_name' => $transaction->street_name,
            'street_number' => $transaction->street_number,
            'address_complement' => $transaction->address_complement,
            'address_reference' => $transaction->address_reference,
            'neighborhood' => $transaction->neighborhood,
            'city' => $transaction->city,
            'state' => $transaction->state,
            'zip_code' => $transaction->zip_code,
            'registry_office' => $transaction->registry_office,
            'registry_registration' => $transaction->registry_registration,
            'financing_type' => $transaction->financing_type,
            'calculation_area_m2' => $transaction->calculation_area_m2 ? (float) $transaction->calculation_area_m2 : null,
            'calculation_area_source' => $transaction->calculation_area_source,
            'price_m2' => $transaction->price_m2 ? (float) $transaction->price_m2 : null,
            'normalized_address' => $transaction->normalized_address,
            'geocoding_status' => $transaction->geocoding_status,
        ];
    }
}
