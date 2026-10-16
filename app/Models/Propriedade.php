<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Propriedade extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'propriedades';

    protected $fillable = [
        'tenant_id',
        'apelido',
        'tipo',
        'area_m2',
        'quartos',
        'banheiros',
        'vagas',
        'valor_aquisicao',
        'data_aquisicao',
        'valor_venda_alvo',
        'status',
        'observacoes',
        'deletado_flg',
    ];

    protected $casts = [
        'area_m2' => 'decimal:2',
        'valor_aquisicao' => 'decimal:2',
        'valor_venda_alvo' => 'decimal:2',
        'data_aquisicao' => 'date',
        'deletado_flg' => 'boolean',
        'data_inclusao' => 'datetime',
        'data_modificacao' => 'datetime',
    ];

    public function endereco(): HasOne
    {
        return $this->hasOne(PropriedadeEndereco::class, 'propriedade_id')
            ->where('deletado_flg', false);
    }

    public function projetos(): HasMany
    {
        return $this->hasMany(Projeto::class, 'propriedade_id');
    }

    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class, 'propriedade_id');
    }

    public function vendas(): HasMany
    {
        return $this->hasMany(Venda::class, 'propriedade_id');
    }
}
