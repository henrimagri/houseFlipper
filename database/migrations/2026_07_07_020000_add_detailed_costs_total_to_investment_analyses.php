<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_analyses', function (Blueprint $table) {
            $table->decimal('detailed_costs_total', 15, 2)->default(0)->after('broker_commission');
        });
        DB::statement('UPDATE investment_analyses SET detailed_costs_total = (SELECT COALESCE(SUM(amount), 0) FROM investment_costs WHERE investment_costs.investment_analysis_id = investment_analyses.id)');
    }

    public function down(): void
    {
        Schema::table('investment_analyses', fn (Blueprint $table) => $table->dropColumn('detailed_costs_total'));
    }
};
