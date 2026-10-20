<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AuctionImport extends Model
{
    use BelongsToTenant;
    protected $guarded = ['id'];
    protected function casts(): array { return ['finished_at' => 'datetime']; }
}
