<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_health_samples', function (Blueprint $t) {
            $t->id();
            $t->string('service', 30);
            $t->timestamp('bucket_at');
            $t->string('status', 30);
            $t->unique(['service', 'bucket_at']);
        });
        Schema::create('platform_privacy_requests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('type', 30);
            $t->string('status', 30)->default('open');
            $t->unsignedBigInteger('assigned_user_id')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'created_at']);
        });
        Schema::create('platform_signal_reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('event_id')->unique();
            $t->unsignedBigInteger('reviewed_by');
            $t->string('classification', 30);
            $t->timestamps();
        });
        Schema::create('platform_export_jobs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedBigInteger('created_by')->index();
            $t->string('report', 30);
            $t->json('filters');
            $t->string('status', 30)->default('queued');
            $t->unsignedInteger('rows')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->string('error_type', 80)->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['platform_export_jobs', 'platform_signal_reviews', 'platform_privacy_requests', 'platform_health_samples'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
