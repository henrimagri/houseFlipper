<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('propriedade_id');
            $table->string('comprador_nome', 200)->nullable();
            $table->string('comprador_documento', 30)->nullable();
            $table->string('comprador_contato', 150)->nullable();
            $table->decimal('valor_venda', 15, 2);
            $table->decimal('valor_comissao', 15, 2)->default(0);
            $table->decimal('valor_impostos', 15, 2)->default(0);
            $table->decimal('outras_taxas', 15, 2)->default(0);
            $table->date('data_proposta')->nullable();
            $table->date('data_fechamento')->nullable();
            $table->date('data_escritura')->nullable();
            $table->string('forma_pagamento', 60)->nullable();
            $table->string('status', 30)->default('proposta'); // proposta, negociacao, fechada, escriturada, cancelada
            $table->text('observacoes')->nullable();
            $table->timestamp('data_inclusao')->useCurrent();
            $table->timestamp('data_modificacao')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('deletado_flg')->default(false);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('propriedade_id')->references('id')->on('propriedades')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('propriedade_id');
            $table->index('status');
            $table->index('data_fechamento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas');
    }
};
