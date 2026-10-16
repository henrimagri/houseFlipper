<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropriedadeEndereco extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'propriedades_enderecos';

    protected $fillable = [
        'tenant_id',
        'propriedade_id',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'estado',
        'pais',
        'latitude',
        'longitude',
        'deletado_flg',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'deletado_flg' => 'boolean',
    ];

    public function propriedade(): BelongsTo
    {
        return $this->belongsTo(Propriedade::class, 'propriedade_id');
    }
}
