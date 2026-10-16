<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaDespesa extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'categorias_despesa';

    protected $fillable = [
        'tenant_id',
        'nome',
        'tipo',
        'ativo_flg',
        'deletado_flg',
    ];

    protected $casts = [
        'ativo_flg' => 'boolean',
        'deletado_flg' => 'boolean',
    ];

    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class, 'categoria_despesa_id');
    }
}
