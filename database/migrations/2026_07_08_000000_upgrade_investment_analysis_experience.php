<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('investment_analyses', 'property_condition')) {
            Schema::table('investment_analyses', function (Blueprint $table) {
                $table->string('property_condition', 40)->nullable()->after('status');
                $table->string('market_region', 120)->nullable()->after('property_condition');
                $table->decimal('average_price_per_m2', 15, 2)->default(0)->after('market_region');
                $table->decimal('average_sold_value', 15, 2)->default(0)->after('average_price_per_m2');
                $table->decimal('suggested_sale_price', 15, 2)->default(0)->after('average_sold_value');
                $table->timestamp('viability_completed_at')->nullable();
                $table->timestamp('financing_completed_at')->nullable();
                $table->timestamp('costs_completed_at')->nullable();
                $table->timestamp('result_completed_at')->nullable();
            });
        }
        Schema::table('financing_details', function (Blueprint $table) {
            $table->index('investment_analysis_id', 'financing_details_analysis_fk_idx');
        });
        Schema::table('financing_details', function (Blueprint $table) {
            $table->dropUnique(['investment_analysis_id']);
            $table->string('label', 120)->nullable()->after('investment_analysis_id');
            $table->boolean('is_selected')->default(false)->after('label');
            $table->index(['investment_analysis_id', 'is_selected']);
        });
        DB::table('financing_details')->update(['is_selected' => true]);
    }

    public function down(): void
    {
        Schema::table('financing_details', function (Blueprint $table) {
            $table->dropIndex(['investment_analysis_id', 'is_selected']);
            $table->dropIndex('financing_details_analysis_fk_idx');
            $table->dropColumn(['label', 'is_selected']);
            $table->unique('investment_analysis_id');
        });
        Schema::table('investment_analyses', function (Blueprint $table) {
            $table->dropColumn(['property_condition', 'market_region', 'average_price_per_m2', 'average_sold_value', 'suggested_sale_price', 'viability_completed_at', 'financing_completed_at', 'costs_completed_at', 'result_completed_at']);
        });
    }
};
