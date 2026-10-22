<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('category', 24);
            $t->string('event', 120);
            $t->string('severity', 16)->default('info');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('product', 32)->nullable();
            $t->uuid('request_id')->nullable()->index();
            $t->string('trace_id', 64)->nullable()->index();
            $t->string('ip_hash', 64)->nullable()->index();
            $t->string('ip_mask', 64)->nullable();
            $t->string('route', 220)->nullable();
            $t->string('method', 10)->nullable();
            $t->unsignedSmallInteger('status')->nullable();
            $t->unsignedInteger('duration_ms')->nullable();
            $t->unsignedBigInteger('request_bytes')->nullable();
            $t->unsignedBigInteger('response_bytes')->nullable();
            $t->string('dedupe_key', 64)->nullable()->unique();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['category', 'created_at']);
            $t->index(['tenant_id', 'created_at']);
            $t->index(['user_id', 'created_at']);
            $t->index(['event', 'created_at']);
            $t->index(['severity', 'created_at']);
            $t->index(['product', 'created_at']);
        });
        Schema::create('platform_incidents', function (Blueprint $t) {
            $t->id();
            $t->string('title', 180);
            $t->string('severity', 16);
            $t->string('status', 24)->default('open');
            $t->unsignedBigInteger('assigned_user_id')->nullable();
            $t->unsignedBigInteger('created_by');
            $t->json('event_ids')->nullable();
            $t->text('notes')->nullable();
            $t->text('resolution')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
        Schema::create('platform_controls', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 24);
            $t->string('key', 120);
            $t->json('value');
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->unique(['kind', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_controls');
        Schema::dropIfExists('platform_incidents');
        Schema::dropIfExists('platform_events');
    }
};
