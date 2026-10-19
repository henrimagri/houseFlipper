<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'documentos';

    protected $fillable = [
        'tenant_id', 'projeto_id', 'enviado_por', 'nome_original', 'caminho', 'mime_type',
        'tamanho_bytes', 'categoria', 'status', 'descricao', 'data_vencimento', 'deletado_flg',
    ];

    protected $casts = [
        'data_vencimento' => 'date',
        'tamanho_bytes' => 'integer',
        'deletado_flg' => 'boolean',
    ];

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }
}
