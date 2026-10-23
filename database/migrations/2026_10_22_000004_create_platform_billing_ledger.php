<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('external_id', 160)->collation(DB::getDriverName() === 'mysql' ? 'utf8mb4_bin' : null);
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained('tenant_product_subscriptions')->restrictOnDelete();
            $table->string('product', 40);
            $table->string('plan', 100);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('type', 20);
            $table->string('status', 20);
            $table->string('evidence_source', 40);
            $table->foreignId('supersedes_id')->nullable()->constrained('billing_transactions')->restrictOnDelete();
            $table->foreignId('refund_of_id')->nullable()->constrained('billing_transactions')->restrictOnDelete();
            $table->dateTime('effective_at');
            $table->dateTime('received_at');
            $table->dateTime('service_period_start')->nullable();
            $table->dateTime('service_period_end')->nullable();
            $table->unsignedTinyInteger('recurring_months')->nullable();
            $table->char('record_hash', 64);
            $table->unique(['provider', 'external_id'], 'billing_provider_external_unique');
            $table->unique('supersedes_id', 'billing_single_successor_unique');
            $table->index(['tenant_id', 'product', 'effective_at'], 'billing_tenant_product_date');
            $table->index(['currency', 'status', 'effective_at'], 'billing_currency_status_date');
            $table->index(['subscription_id', 'service_period_end'], 'billing_subscription_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_transactions');
    }
};
