<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('draft');
            $table->boolean('is_primary')->default(false);
            foreach (['purchase_price', 'down_payment', 'financed_amount', 'renovation_estimate', 'documentation_costs', 'taxes_costs', 'broker_commission', 'expected_sale_price', 'expected_monthly_costs', 'expected_installment_value', 'expected_outstanding_balance', 'total_own_capital_invested', 'expected_net_profit'] as $column) {
                $table->decimal($column, 15, 2)->default(0);
            }
            $table->unsignedSmallInteger('expected_months_until_sale')->default(1);
            foreach (['expected_roi_percentage', 'expected_monthly_roi_percentage', 'expected_annualized_roi_percentage'] as $column) {
                $table->decimal($column, 12, 4)->default(0);
            }
            $table->string('decision', 30)->default('rejected');
            $table->text('decision_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'is_primary']);
        });
        Schema::create('financing_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_analysis_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('financing_system', 20)->nullable();
            $table->decimal('interest_rate_monthly', 10, 4)->default(0);
            $table->decimal('interest_rate_yearly', 10, 4)->default(0);
            $table->unsignedSmallInteger('total_installments')->default(0);
            $table->decimal('installment_value', 15, 2)->default(0);
            $table->unsignedSmallInteger('paid_installments')->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->decimal('payoff_amount', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('investment_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('due_date')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_status', 20)->default('pending');
            $table->timestamps();
        });
        Schema::create('investment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_analysis_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->date('sale_date')->nullable();
            foreach (['sale_costs', 'payoff_amount', 'total_own_capital_invested', 'net_amount_received_after_payoff', 'net_profit'] as $column) {
                $table->decimal($column, 15, 2)->default(0);
            }
            foreach (['roi_percentage', 'monthly_roi_percentage', 'annualized_roi_percentage'] as $column) {
                $table->decimal($column, 12, 4)->default(0);
            }
            $table->unsignedSmallInteger('flip_duration_months')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_results');
        Schema::dropIfExists('investment_costs');
        Schema::dropIfExists('financing_details');
        Schema::dropIfExists('investment_analyses');
    }
};
