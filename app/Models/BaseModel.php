<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class BaseModel extends EloquentModel
{
    public const CREATED_AT = 'data_inclusao';
    public const UPDATED_AT = 'data_modificacao';

    /**
     * Os modelos do dom\u00ednio usam a coluna deletado_flg ao inv\u00e9s do soft-delete padr\u00e3o.
     * Para deletar logicamente: $model->update(['deletado_flg' => true]).
     */
    public function scopeAtivos($query)
    {
        return $query->where($this->getTable() . '.deletado_flg', false);
    }
}
