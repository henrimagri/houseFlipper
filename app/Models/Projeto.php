<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Projeto extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'projetos';

    protected $fillable = [
        'tenant_id',
        'propriedade_id',
        'nome',
        'descricao',
        'orcamento_total',
        'custo_real',
        'data_inicio',
        'data_termino_prevista',
        'data_termino_real',
        'status',
        'deletado_flg',
    ];

    protected $casts = [
        'orcamento_total' => 'decimal:2',
        'custo_real' => 'decimal:2',
        'data_inicio' => 'date',
        'data_termino_prevista' => 'date',
        'data_termino_real' => 'date',
        'deletado_flg' => 'boolean',
    ];

    public function propriedade(): BelongsTo
    {
        return $this->belongsTo(Propriedade::class, 'propriedade_id');
    }

    public function etapas(): HasMany
    {
        return $this->hasMany(ProjetoEtapa::class, 'projeto_id')->orderBy('ordem');
    }

    public function tarefas(): HasMany
    {
        return $this->hasMany(Tarefa::class, 'projeto_id');
    }

    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class, 'projeto_id');
    }
}
