<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_calendario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('projeto_id')->nullable()->constrained('projetos')->nullOnDelete();
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo', 180);
            $table->text('descricao')->nullable();
            $table->string('categoria', 30);
            $table->dateTime('inicio_em');
            $table->dateTime('fim_em')->nullable();
            $table->boolean('dia_inteiro')->default(false);
            $table->string('local', 180)->nullable();
            $table->string('endereco', 500)->nullable();
            $table->string('status', 30)->default('agendado');
            $table->boolean('deletado_flg')->default(false);
            $table->timestamp('data_inclusao')->nullable();
            $table->timestamp('data_modificacao')->nullable();
            $table->index(['tenant_id', 'deletado_flg', 'inicio_em'], 'eventos_tenant_inicio_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_calendario');
    }
};
