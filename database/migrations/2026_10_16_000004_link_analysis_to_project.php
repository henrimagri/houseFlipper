<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_analyses', function (Blueprint $table) {
            $table->foreignId('projeto_id')->nullable()->unique()->constrained('projetos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('investment_analyses', function (Blueprint $table) {
            $table->dropForeign(['projeto_id']);
            $table->dropUnique(['projeto_id']);
            $table->dropColumn('projeto_id');
        });
    }
};
