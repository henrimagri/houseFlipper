<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projetos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('propriedade_id');
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->decimal('orcamento_total', 15, 2)->default(0);
            $table->date('data_inicio')->nullable();
            $table->date('data_termino_prevista')->nullable();
            $table->date('data_termino_real')->nullable();
            $table->string('status', 30)->default('planejamento'); // planejamento, em_andamento, pausado, concluido, cancelado
            $table->timestamp('data_inclusao')->useCurrent();
            $table->timestamp('data_modificacao')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('deletado_flg')->default(false);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('propriedade_id')->references('id')->on('propriedades')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('propriedade_id');
            $table->index('status');
            $table->index('deletado_flg');
        });

        Schema::create('projetos_etapas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('projeto_id');
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->integer('ordem')->default(0);
            $table->decimal('orcamento', 15, 2)->default(0);
            $table->date('data_inicio_prevista')->nullable();
            $table->date('data_termino_prevista')->nullable();
            $table->date('data_inicio_real')->nullable();
            $table->date('data_termino_real')->nullable();
            $table->string('status', 30)->default('pendente'); // pendente, em_andamento, concluida, bloqueada
            $table->integer('progresso_pct')->default(0);
            $table->timestamp('data_inclusao')->useCurrent();
            $table->timestamp('data_modificacao')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('deletado_flg')->default(false);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('projeto_id')->references('id')->on('projetos')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index('projeto_id');
            $table->index('status');
        });

        Schema::create('tarefas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('projeto_id');
            $table->unsignedBigInteger('etapa_id')->nullable();
            $table->unsignedBigInteger('responsavel_user_id')->nullable();
            $table->string('titulo', 200);
            $table->text('descricao')->nullable();
            $table->date('data_prevista')->nullable();
            $table->date('data_conclusao')->nullable();
            $table->string('prioridade', 20)->default('media'); // baixa, media, alta, critica
            $table->string('status', 30)->default('pendente'); // pendente, em_andamento, concluida, cancelada
            $table->decimal('custo_estimado', 15, 2)->nullable();
            $table->decimal('custo_real', 15, 2)->nullable();
            $table->timestamp('data_inclusao')->useCurrent();
            $table->timestamp('data_modificacao')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('deletado_flg')->default(false);

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('projeto_id')->references('id')->on('projetos')->cascadeOnDelete();
            $table->foreign('etapa_id')->references('id')->on('projetos_etapas')->nullOnDelete();
            $table->foreign('responsavel_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('tenant_id');
            $table->index('projeto_id');
            $table->index('etapa_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarefas');
        Schema::dropIfExists('projetos_etapas');
        Schema::dropIfExists('projetos');
    }
};
