<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjetoEtapa extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'projetos_etapas';

    protected $fillable = [
        'tenant_id',
        'projeto_id',
        'nome',
        'descricao',
        'ordem',
        'orcamento',
        'custo_real',
        'data_inicio_prevista',
        'data_termino_prevista',
        'data_inicio_real',
        'data_termino_real',
        'status',
        'progresso_pct',
        'deletado_flg',
    ];

    protected $casts = [
        'orcamento' => 'decimal:2',
        'custo_real' => 'decimal:2',
        'data_inicio_prevista' => 'date',
        'data_termino_prevista' => 'date',
        'data_inicio_real' => 'date',
        'data_termino_real' => 'date',
        'deletado_flg' => 'boolean',
    ];

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }

    public function tarefas(): HasMany
    {
        return $this->hasMany(Tarefa::class, 'etapa_id');
    }
}
