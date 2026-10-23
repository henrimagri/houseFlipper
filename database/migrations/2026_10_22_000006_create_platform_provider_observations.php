<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_deliveries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('provider', 30);
            $t->string('message_hash', 64)->nullable()->unique();
            $t->unsignedBigInteger('tenant_id')->nullable()->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('product', 40)->nullable();
            $t->string('status', 30);
            $t->string('bounce_type', 30)->nullable();
            $t->timestamp('occurred_at')->nullable();
            $t->timestamps();
        });
        Schema::create('platform_provider_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 30);
            $t->string('external_id', 120)->collation(DB::getDriverName() === 'mysql' ? 'utf8mb4_bin' : null);
            $t->string('event_type', 80);
            $t->string('status', 40);
            $t->timestamp('created_at');
            $t->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_provider_receipts');
        Schema::dropIfExists('platform_email_deliveries');
    }
};
