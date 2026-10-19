<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoCalendario extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'eventos_calendario';

    protected $fillable = [
        'tenant_id', 'projeto_id', 'criado_por', 'titulo', 'descricao', 'categoria', 'inicio_em',
        'fim_em', 'dia_inteiro', 'local', 'endereco', 'status', 'deletado_flg',
    ];

    protected $casts = [
        'inicio_em' => 'datetime',
        'fim_em' => 'datetime',
        'dia_inteiro' => 'boolean',
        'deletado_flg' => 'boolean',
    ];

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }
}
