<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('property_type', 30);
            $table->string('source', 30)->default('manual');
            $table->string('source_url', 2048)->nullable();
            $table->string('status', 30)->default('new');
            $table->string('address')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city', 120)->nullable();
            $table->char('state', 2)->nullable();
            $table->string('zip_code', 12)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('asking_price', 15, 2)->nullable();
            $table->decimal('estimated_market_price', 15, 2)->nullable();
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->unsignedSmallInteger('parking_spaces')->nullable();
            $table->string('conservation_status', 50)->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->text('discarded_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_favorite']);
            $table->index(['city', 'neighborhood']);
        });

        Schema::create('opportunity_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();
            $table->index(['opportunity_id', 'order']);
        });

        Schema::create('opportunity_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['opportunity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_status_histories');
        Schema::dropIfExists('opportunity_photos');
        Schema::dropIfExists('opportunities');
    }
};
