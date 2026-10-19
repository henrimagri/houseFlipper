<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('projeto_id')->nullable()->constrained('projetos')->nullOnDelete();
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nome_original', 255);
            $table->string('caminho', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('tamanho_bytes');
            $table->string('categoria', 80);
            $table->string('status', 30)->default('ativo');
            $table->text('descricao')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->boolean('deletado_flg')->default(false);
            $table->timestamp('data_inclusao')->nullable();
            $table->timestamp('data_modificacao')->nullable();
            $table->index(['tenant_id', 'deletado_flg', 'data_inclusao'], 'documentos_tenant_lista_idx');
            $table->unique('caminho');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
