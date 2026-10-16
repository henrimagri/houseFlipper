<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projetos', function (Blueprint $table) {
            $table->decimal('custo_real', 15, 2)->default(0)->after('orcamento_total');
        });

        Schema::table('projetos_etapas', function (Blueprint $table) {
            $table->decimal('custo_real', 15, 2)->default(0)->after('orcamento');
        });
    }

    public function down(): void
    {
        Schema::table('projetos', function (Blueprint $table) {
            $table->dropColumn('custo_real');
        });
        Schema::table('projetos_etapas', function (Blueprint $table) {
            $table->dropColumn('custo_real');
        });
    }
};
