<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Venda extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'vendas';

    protected $fillable = [
        'tenant_id',
        'propriedade_id',
        'comprador_nome',
        'comprador_documento',
        'comprador_contato',
        'valor_venda',
        'valor_comissao',
        'valor_impostos',
        'outras_taxas',
        'data_proposta',
        'data_fechamento',
        'data_escritura',
        'forma_pagamento',
        'status',
        'observacoes',
        'deletado_flg',
    ];

    protected $casts = [
        'valor_venda' => 'decimal:2',
        'valor_comissao' => 'decimal:2',
        'valor_impostos' => 'decimal:2',
        'outras_taxas' => 'decimal:2',
        'data_proposta' => 'date',
        'data_fechamento' => 'date',
        'data_escritura' => 'date',
        'deletado_flg' => 'boolean',
    ];

    public function propriedade(): BelongsTo
    {
        return $this->belongsTo(Propriedade::class, 'propriedade_id');
    }

    public function valorLiquido(): float
    {
        return (float) $this->valor_venda
            - (float) $this->valor_comissao
            - (float) $this->valor_impostos
            - (float) $this->outras_taxas;
    }
}
