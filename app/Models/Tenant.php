<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends BaseModel
{
    protected $table = 'tenants';

    protected $fillable = [
        'nome',
        'slug',
        'documento',
        'email_contato',
        'ativo_flg',
        'deletado_flg',
    ];

    protected $casts = [
        'ativo_flg' => 'boolean',
        'deletado_flg' => 'boolean',
        'data_inclusao' => 'datetime',
        'data_modificacao' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function propriedades(): HasMany
    {
        return $this->hasMany(Propriedade::class, 'tenant_id');
    }
}
