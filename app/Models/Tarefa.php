<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarefa extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tarefas';

    protected $fillable = [
        'tenant_id',
        'projeto_id',
        'etapa_id',
        'responsavel_user_id',
        'titulo',
        'descricao',
        'data_prevista',
        'data_conclusao',
        'prioridade',
        'status',
        'custo_estimado',
        'custo_real',
        'deletado_flg',
    ];

    protected $casts = [
        'data_prevista' => 'date',
        'data_conclusao' => 'date',
        'custo_estimado' => 'decimal:2',
        'custo_real' => 'decimal:2',
        'deletado_flg' => 'boolean',
    ];

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(ProjetoEtapa::class, 'etapa_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }
}
