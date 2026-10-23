<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_heartbeats', function (Blueprint $t) {
            $t->uuid('worker_id')->primary();
            $t->string('type', 16)->index();
            $t->string('host_hash', 64);
            $t->string('version', 80);
            $t->timestamp('started_at');
            $t->timestamp('last_seen')->index();
        });
        Schema::create('platform_request_aggregates', function (Blueprint $t) {
            $t->id();
            $t->string('group_key', 64)->unique();
            $t->timestamp('bucket_at')->index();
            $t->string('route', 220);
            $t->string('method', 10);
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('product', 32)->nullable();
            $t->unsignedSmallInteger('status')->nullable();
            $t->unsignedBigInteger('request_count');
            $t->unsignedBigInteger('error_count');
            $t->unsignedBigInteger('latency_samples');
            $t->unsignedBigInteger('duration_sum_ms');
            $t->json('histogram');
            $t->timestamp('updated_at');
            $t->index(['tenant_id', 'bucket_at']);
            $t->index(['product', 'bucket_at']);
        });
        Schema::create('platform_aggregate_cursors', function (Blueprint $t) {
            $t->string('name', 40)->primary();
            $t->unsignedBigInteger('last_event_id')->default(0);
            $t->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_aggregate_cursors');
        Schema::dropIfExists('platform_request_aggregates');
        Schema::dropIfExists('platform_heartbeats');
    }
};
