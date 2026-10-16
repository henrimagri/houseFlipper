<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itbi_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 80);
            $table->text('source_url');
            $table->string('source_filename');
            $table->string('reference_period', 80)->nullable();
            $table->char('file_hash', 64)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('status', 40)->default('discovered');
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_finished_at')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index('reference_period');
            $table->index('file_hash');
        });

        Schema::create('real_estate_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained('itbi_import_batches')->nullOnDelete();
            $table->string('source_type', 40)->default('itbi');
            $table->string('source_name', 160);
            $table->string('source_reference', 191);
            $table->string('property_sql', 80)->nullable();
            $table->date('transaction_date')->nullable();
            $table->decimal('declared_transaction_value', 15, 2)->nullable();
            $table->decimal('tax_base_value', 15, 2)->nullable();
            $table->string('property_type', 80)->nullable();
            $table->string('transaction_nature', 120)->nullable();
            $table->string('street_type', 40)->nullable();
            $table->string('street_name', 180)->nullable();
            $table->string('street_number', 40)->nullable();
            $table->string('address_complement', 120)->nullable();
            $table->string('address_reference', 160)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120)->default('São Paulo');
            $table->char('state', 2)->default('SP');
            $table->string('zip_code', 12)->nullable();
            $table->string('financing_type', 160)->nullable();
            $table->string('registry_office', 120)->nullable();
            $table->string('registry_registration', 80)->nullable();
            $table->decimal('land_area_m2', 12, 2)->nullable();
            $table->decimal('built_area_m2', 12, 2)->nullable();
            $table->decimal('private_area_m2', 12, 2)->nullable();
            $table->decimal('calculation_area_m2', 12, 2)->nullable();
            $table->string('calculation_area_source', 40)->nullable();
            $table->decimal('price_m2', 15, 2)->nullable();
            $table->string('normalized_address')->nullable();
            $table->char('address_hash', 64)->nullable();
            $table->string('building_key')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_place_id')->nullable();
            $table->string('geocoding_status', 40)->nullable();
            $table->string('geocoding_precision', 80)->nullable();
            $table->unsignedTinyInteger('geocoding_confidence')->nullable();
            $table->timestamp('geocoded_at')->nullable();
            $table->text('iptu_use_description')->nullable();
            $table->text('iptu_pattern_description')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_name', 'source_reference'], 'transactions_source_unique');
            $table->index('property_sql');
            $table->index('transaction_date');
            $table->index('address_hash');
            $table->index('building_key');
            $table->index(['latitude', 'longitude']);
            $table->index('district');
            $table->index('neighborhood');
            $table->index('property_type');
            $table->index('price_m2');
        });

        Schema::create('geocoding_cache', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 80);
            $table->char('address_hash', 64);
            $table->string('original_address')->nullable();
            $table->string('normalized_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('place_id')->nullable();
            $table->string('location_type', 80)->nullable();
            $table->boolean('partial_match')->default(false);
            $table->string('formatted_address')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('status', 40)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('queried_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'address_hash']);
            $table->index('status');
        });

        Schema::create('property_market_statistics', function (Blueprint $table) {
            $table->id();
            $table->string('geographic_scope_type', 40);
            $table->string('geographic_scope_key');
            $table->string('property_type', 80)->nullable();
            $table->date('reference_date')->nullable();
            $table->unsignedInteger('radius_meters')->nullable();
            $table->unsignedInteger('transaction_count')->default(0);
            $table->decimal('median_price', 15, 2)->nullable();
            $table->decimal('average_price', 15, 2)->nullable();
            $table->decimal('median_price_m2', 15, 2)->nullable();
            $table->decimal('average_price_m2', 15, 2)->nullable();
            $table->decimal('min_price_m2', 15, 2)->nullable();
            $table->decimal('max_price_m2', 15, 2)->nullable();
            $table->decimal('percentile_25_price_m2', 15, 2)->nullable();
            $table->decimal('percentile_75_price_m2', 15, 2)->nullable();
            $table->decimal('standard_deviation_price_m2', 15, 2)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['geographic_scope_type', 'geographic_scope_key'], 'market_stats_scope_index');
            $table->index(['property_type', 'reference_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_market_statistics');
        Schema::dropIfExists('geocoding_cache');
        Schema::dropIfExists('real_estate_transactions');
        Schema::dropIfExists('itbi_import_batches');
    }
};
