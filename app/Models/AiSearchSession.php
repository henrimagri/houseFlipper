<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSearchSession extends Model
{
    protected $fillable = ['user_id', 'tenant_id', 'organization_id', 'product_slug', 'title', 'status', 'active_agent', 'prompt_version', 'criteria', 'last_listings', 'last_recommendation', 'expires_at'];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'last_listings' => 'array',
            'last_recommendation' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiSearchMessage::class);
    }
}
