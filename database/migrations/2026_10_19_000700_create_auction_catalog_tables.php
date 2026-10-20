<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auction_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('original_name', 180);
            $table->string('sha256', 64);
            $table->string('status', 30)->default('processing');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_rejected')->default(0);
            $table->text('error_summary')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'provider', 'created_at']);
        });

        Schema::create('auction_listings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('source_id', 180);
            $table->string('title');
            $table->string('property_type', 60)->nullable();
            $table->string('city', 120)->nullable();
            $table->char('state', 2)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->unsignedBigInteger('appraisal_cents')->nullable();
            $table->string('sale_method', 120)->nullable();
            $table->dateTime('auction_at')->nullable();
            $table->string('status', 40)->default('available');
            $table->string('official_url', 2048)->nullable();
            $table->string('notice_url', 2048)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('collected_at');
            $table->json('source_payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'source_id']);
            $table->index(['state', 'city', 'status']);
            $table->index(['provider', 'sale_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_listings');
        Schema::dropIfExists('auction_imports');
    }
};
