<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('nome', 160);
            $table->string('categoria', 80);
            $table->string('contato', 160)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('email', 254)->nullable();
            $table->decimal('avaliacao', 2, 1)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo_flg')->default(true);
            $table->boolean('deletado_flg')->default(false);
            $table->timestamp('data_inclusao')->nullable();
            $table->timestamp('data_modificacao')->nullable();
            $table->index(['tenant_id', 'deletado_flg', 'nome'], 'fornecedores_tenant_lista_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
    }
};
