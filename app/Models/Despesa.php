<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Despesa extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'despesas';

    protected $fillable = [
        'tenant_id',
        'propriedade_id',
        'projeto_id',
        'etapa_id',
        'tarefa_id',
        'categoria_despesa_id',
        'descricao',
        'valor',
        'data_despesa',
        'data_pagamento',
        'forma_pagamento',
        'status_pagamento',
        'documento_numero',
        'observacoes',
        'deletado_flg',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_despesa' => 'date',
        'data_pagamento' => 'date',
        'deletado_flg' => 'boolean',
    ];

    public function propriedade(): BelongsTo
    {
        return $this->belongsTo(Propriedade::class, 'propriedade_id');
    }

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaDespesa::class, 'categoria_despesa_id');
    }
}
