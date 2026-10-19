<?php

use App\Services\RealEstateData\ItbiTransactionNormalizerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('real_estate_transactions')
            ->where(fn ($query) => $query->whereNull('property_sql')->orWhere('property_sql', ''))
            ->orderBy('id')->chunkById(200, function ($rows): void {
                DB::transaction(function () use ($rows): void {
                    foreach ($rows as $row) {
                        $data = (array) $row;
                        $data['transaction_date'] = $data['transaction_date'] ? substr($data['transaction_date'], 0, 10) : null;
                        $data['raw_payload'] = json_decode($data['raw_payload'] ?? '{}', true) ?? [];
                        foreach (['declared_transaction_value', 'tax_base_value'] as $field) {
                            $data[$field] = $data[$field] !== null ? (float) $data[$field] : null;
                        }
                        $reference = app(ItbiTransactionNormalizerService::class)->sourceReference($data);
                        DB::table('real_estate_transactions')->where('id', $row->id)->update(['source_reference' => $reference]);
                    }
                });
            });
    }

    public function down(): void
    {
        // Keep corrected identities; do not recreate collision-prone keys.
    }
};
