<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nome', 150);
            $table->string('slug', 80)->unique();
            $table->string('documento', 30)->nullable();
            $table->string('email_contato', 150)->nullable();
            $table->boolean('ativo_flg')->default(true);
            $table->timestamp('data_inclusao')->useCurrent();
            $table->timestamp('data_modificacao')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('deletado_flg')->default(false);

            $table->index('ativo_flg');
            $table->index('deletado_flg');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
