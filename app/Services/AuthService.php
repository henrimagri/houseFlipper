<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
            $user->save();

            $token = $user->createToken($data['device_name'] ?? 'default')->plainTextToken;

            return ['tenant' => $tenant, 'user' => $user, 'token' => $token];
        });
    }

    public function login(string $email, string $password, ?string $deviceName = null): ?array
    {
        $user = User::where('email', $email)->where('deletado_flg', false)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $token = $user->createToken($deviceName ?? 'default')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
