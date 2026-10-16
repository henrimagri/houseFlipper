<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_despesa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->string('nome', 100);
            $table->string('tipo', 30)->default('reforma'); // aquisicao, reforma, holding, taxas, venda, outros
            $table->boolean('ativo_flg')->default(true);
            $table->timestamp('data_inclusao')->useCurrent();
            $table->timestamp('data_modificacao')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('deletado_flg')->default(false);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('tipo');
            $table->unique(['tenant_id', 'nome', 'deletado_flg']);
        });

        Schema::create('despesas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('propriedade_id')->nullable();
            $table->unsignedBigInteger('projeto_id')->nullable();
            $table->unsignedBigInteger('etapa_id')->nullable();
            $table->unsignedBigInteger('tarefa_id')->nullable();
            $table->unsignedBigInteger('categoria_despesa_id');
            $table->string('descricao', 250);
            $table->decimal('valor', 15, 2);
            $table->date('data_despesa');
            $table->date('data_pagamento')->nullable();
            $table->string('forma_pagamento', 40)->nullable();
            $table->string('status_pagamento', 30)->default('pendente'); // pendente, pago, atrasado, cancelado
            $table->string('documento_numero', 80)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamp('data_inclusao')->useCurrent();
            $table->timestamp('data_modificacao')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('deletado_flg')->default(false);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('propriedade_id')->references('id')->on('propriedades')->nullOnDelete();
            $table->foreign('projeto_id')->references('id')->on('projetos')->nullOnDelete();
            $table->foreign('etapa_id')->references('id')->on('projetos_etapas')->nullOnDelete();
            $table->foreign('tarefa_id')->references('id')->on('tarefas')->nullOnDelete();
            $table->foreign('categoria_despesa_id')->references('id')->on('categorias_despesa');
            $table->index('tenant_id');
            $table->index('propriedade_id');
            $table->index('projeto_id');
            $table->index('data_despesa');
            $table->index('status_pagamento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('despesas');
        Schema::dropIfExists('categorias_despesa');
    }
};
