<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'nome' => $data['tenant_nome'],
                'slug' => $data['tenant_slug'],
            ]);

            $user = new User([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->forceFill([
                'requires_email_verification' => true,
                'registration_profile' => [
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'intended_product' => $data['intended_product'] ?? null,
                    'professional_name' => $data['professional_name'] ?? null,
                    'creci' => $data['creci'] ?? null,
                    'city' => $data['city'] ?? null,
                    'company_name' => $data['company_name'] ?? null,
                    'region_preference' => $data['region_preference'] ?? null,
                    'usage_goal' => $data['usage_goal'] ?? null,
                ],
            ]);
            $user->save();
            if (! empty($data['analytics_session'])) {
                DB::table('platform_consent_attributions')->where('session_hash', hash_hmac('sha256', $data['analytics_session'], config('app.key')))
                    ->whereNull('user_id')->where('consented_at', '>=', now()->subDays(90))
                    ->update(['user_id' => $user->id, 'tenant_id' => $tenant->id]);
            }

            $token = $user->createToken($data['device_name'] ?? 'default', ['*'], now()->addMinutes(config('sanctum.expiration')))->plainTextToken;

            return ['tenant' => $tenant, 'user' => $user, 'token' => $token];
        });
    }

    public function login(string $email, string $password, ?string $deviceName = null): ?array
    {
        $matches = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->where('deletado_flg', false)->limit(2)->get();
        if ($matches->count() !== 1) {
            return null;
        }
        $user = $matches->first();

        if (! $user->ativo_flg || ! Hash::check($password, $user->password)) {
            return null;
        }

        if ($user->tenant_id && (! $user->tenant || ! $user->tenant->ativo_flg || $user->tenant->deletado_flg)) {
            return null;
        }

        $token = $user->createToken($deviceName ?? 'default', ['*'], now()->addMinutes(config('sanctum.expiration')))->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user, Request $request): void
    {
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();

            return;
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    public function sessions(User $user): array
    {
        $currentId = $user->currentAccessToken() instanceof PersonalAccessToken
            ? $user->currentAccessToken()->id
            : null;

        return $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'current' => $token->id === $currentId,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ])->all();
    }

    public function revokeSession(User $user, int $tokenId): bool
    {
        return (bool) $user->tokens()->whereKey($tokenId)->delete();
    }

    public function logoutAll(User $user, Request $request): void
    {
        $user->tokens()->delete();
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    public function logoutOthers(User $user): int
    {
        $currentId = $user->currentAccessToken() instanceof PersonalAccessToken
            ? $user->currentAccessToken()->id
            : null;

        return $user->tokens()
            ->when($currentId, fn ($query) => $query->whereKeyNot($currentId))
            ->delete();
    }
}
