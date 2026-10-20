<?php

namespace App\Models;

use App\Models\PlatformAdmin;
use App\Notifications\ResetPasswordEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    protected $attributes = ['ativo_flg' => true, 'deletado_flg' => false];

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'tenant_id',
        'last_product_slug',
        'name',
        'email',
        'password',
        'ativo_flg',
        'deletado_flg',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'requires_email_verification' => 'boolean',
            'registration_profile' => 'array',
            'password' => 'hashed',
            'ativo_flg' => 'boolean',
            'deletado_flg' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordEmail((string) $token));
    }

    public function isPlatformAdmin(): bool
    {
        if (PlatformAdmin::query()->where('user_id', $this->id)->exists()) {
            return true;
        }

        $allowlist = config('platform.admin_emails', []);

        return in_array(mb_strtolower((string) $this->email), $allowlist, true);
    }
}
