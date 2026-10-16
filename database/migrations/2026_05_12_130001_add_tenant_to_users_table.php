<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->boolean('ativo_flg')->default(true)->after('password');
            $table->boolean('deletado_flg')->default(false)->after('ativo_flg');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('deletado_flg');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'ativo_flg', 'deletado_flg']);
        });
    }
};
