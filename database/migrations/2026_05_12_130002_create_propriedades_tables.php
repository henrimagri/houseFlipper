<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propriedades', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('apelido', 120);
            $table->string('tipo', 40)->nullable(); // casa, apto, terreno, etc
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->integer('quartos')->nullable();
            $table->integer('banheiros')->nullable();
            $table->integer('vagas')->nullable();
            $table->decimal('valor_aquisicao', 15, 2)->nullable();
            $table->date('data_aquisicao')->nullable();
            $table->decimal('valor_venda_alvo', 15, 2)->nullable();
            $table->string('status', 30)->default('prospeccao'); // prospeccao, adquirida, em_reforma, a_venda, vendida
            $table->text('observacoes')->nullable();
            $table->timestamp('data_inclusao')->useCurrent();
            $table->timestamp('data_modificacao')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('deletado_flg')->default(false);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('status');
            $table->index('deletado_flg');
        });

        Schema::create('propriedades_enderecos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('propriedade_id');
            $table->string('cep', 12)->nullable();
            $table->string('logradouro', 200)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('pais', 60)->default('Brasil');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('data_inclusao')->useCurrent();
            $table->timestamp('data_modificacao')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('deletado_flg')->default(false);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('propriedade_id')->references('id')->on('propriedades')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->unique(['propriedade_id', 'deletado_flg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propriedades_enderecos');
        Schema::dropIfExists('propriedades');
    }
};
