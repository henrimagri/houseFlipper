<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_persists_complete_brazilian_profile(): void
    {
        $this->postJson('/api/auth/register', [
            'tenant_nome' => 'Operação Completa',
            'name' => 'Nova Pessoa',
            'email' => 'nova@example.test',
            'phone' => '(11) 98765-4321',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'intended_product' => 'corretor',
            'professional_name' => 'Nova Pessoa',
            'city' => 'São Paulo',
            'consent' => true,
            'device_name' => 'Teste automatizado',
            'address' => ['postal_code' => '01310100', 'street' => 'Avenida Paulista', 'number' => '1000', 'complement' => 'Sem complemento', 'neighborhood' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP'],
        ])->assertCreated()->assertJsonPath('requires_email_verification', true);

        $user = User::query()->where('email', 'nova@example.test')->firstOrFail();
        $this->assertSame('11987654321', $user->registration_profile['phone']);
        $this->assertSame('01310100', $user->registration_profile['address']['postal_code']);
    }

    public function test_user_can_update_profile_and_notification_preferences(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $this->withToken($token)->patchJson('/api/auth/profile', [
            'name' => 'Nome Atualizado',
            'phone' => '11987654321',
            'preferences' => ['email_notifications' => false, 'product_updates' => true],
        ])->assertOk()->assertJsonPath('user.name', 'Nome Atualizado');

        $user->refresh();
        $this->assertSame('11987654321', $user->registration_profile['phone']);
        $this->assertFalse($user->registration_profile['preferences']['email_notifications']);
    }

    public function test_password_change_checks_current_password(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $this->withToken($token)->putJson('/api/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->withToken($token)->putJson('/api/auth/password', [
            'current_password' => 'password-123',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_user_can_upload_a_valid_public_avatar(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $response = $this->withToken($token)->post('/api/auth/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.png', 120, 120)->size(100),
        ], ['Accept' => 'application/json'])->assertOk();

        $url = $response->json('avatar_url');
        $this->assertMatchesRegularExpression('#^/uploads/avatars/[a-f0-9-]+\.png$#', $url);
        $this->assertFileExists(public_path(ltrim($url, '/')));
        @unlink(public_path(ltrim($url, '/')));
        $this->assertSame($url, $user->fresh()->registration_profile['avatar_url']);
    }

    private function authenticatedUser(): array
    {
        $tenant = Tenant::query()->create(['nome' => 'Conta', 'slug' => 'profile-settings']);
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Usuário',
            'email' => 'profile@example.test',
            'password' => 'password-123',
            'registration_profile' => [],
        ]);

        return [$user, $user->createToken('test')->plainTextToken];
    }
}
