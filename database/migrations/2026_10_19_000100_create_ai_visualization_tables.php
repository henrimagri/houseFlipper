<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_product_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('product_slug', 40);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'product_slug']);
        });

        Schema::create('ai_search_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id');
            $table->string('product_slug', 40)->default('house_flipper');
            $table->json('criteria')->nullable();
            $table->json('last_listings')->nullable();
            $table->json('last_recommendation')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_search_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_search_session_id')->constrained('ai_search_sessions')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->json('criteria_snapshot')->nullable();
            $table->json('listing_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('property_visualizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('status', 40)->default('insufficient');
            $table->string('level', 40)->default('fallback_gallery');
            $table->unsignedTinyInteger('quality_score')->nullable();
            $table->string('origin_label', 120)->nullable();
            $table->text('limitations')->nullable();
            $table->json('rooms')->nullable();
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id']);
            $table->index(['tenant_id', 'subject_type']);
        });

        Schema::create('property_visualization_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_visualization_id')->constrained('property_visualizations')->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('room_key', 80)->nullable();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedInteger('size_bytes');
            $table->string('checksum', 64)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('property_photo_analyses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('source_photo_id')->nullable();
            $table->string('source_name', 180)->nullable();
            $table->string('room_guess', 80)->nullable();
            $table->json('elements')->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('origin', 40);
            $table->string('quality', 40)->default('low');
            $table->json('limitations')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_photo_analyses');
        Schema::dropIfExists('property_visualization_assets');
        Schema::dropIfExists('property_visualizations');
        Schema::dropIfExists('ai_search_messages');
        Schema::dropIfExists('ai_search_sessions');
        Schema::dropIfExists('tenant_product_subscriptions');
    }
};
