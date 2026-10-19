<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

class Fornecedor extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fornecedores';

    protected $fillable = [
        'tenant_id',
        'nome',
        'categoria',
        'contato',
        'telefone',
        'email',
        'avaliacao',
        'observacoes',
        'ativo_flg',
        'deletado_flg',
    ];

    protected $casts = [
        'avaliacao' => 'decimal:1',
        'ativo_flg' => 'boolean',
        'deletado_flg' => 'boolean',
    ];
}
