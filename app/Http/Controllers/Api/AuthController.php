<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Ai\ProductEntitlement;
use App\Services\AuthService;
use App\Services\RegistrationVerificationService;
use App\Support\ProductContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ProductEntitlement $entitlement,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $this->authService->register($request->validated());
        $delivery = app(RegistrationVerificationService::class)->send($data['user']);
        $request->setUserResolver(fn () => $data['user']);
        app('request')->attributes->set('platform_authenticated_user', $data['user']);

        return response()->json([
            'tenant' => [
                'id' => $data['tenant']->id,
                'nome' => $data['tenant']->nome,
                'slug' => $data['tenant']->slug,
            ],
            'user' => [
                'id' => $data['user']->id,
                'name' => $data['user']->name,
                'email' => $data['user']->email,
            ],
            'token' => $data['token'],
            'requires_email_verification' => true,
            'verification_delivery' => $delivery,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('email'),
            $request->string('password'),
            $request->string('device_name', 'default'),
        );

        if (! $result) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        $request->setUserResolver(fn () => $result['user']);
        app('request')->attributes->set('platform_authenticated_user', $result['user']);

        return response()->json([
            'user' => [
                'id' => $result['user']->id,
                'tenant_id' => $result['user']->tenant_id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
            ],
            'token' => $result['token'],
            'requires_email_verification' => $result['user']->requires_email_verification && ! $result['user']->hasVerifiedEmail(),
            ...$this->entitlement->sessionPayload($result['user']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user(), $request);

        return response()->json(['message' => 'Sessão encerrada']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('tenant');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'requires_email_verification' => $user->requires_email_verification && ! $user->hasVerifiedEmail(),
            'email_verified' => $user->hasVerifiedEmail(),
            'registration_profile' => $user->registration_profile,
            'avatar_url' => $user->registration_profile['avatar_url'] ?? null,
            'tenant' => $user->tenant ? [
                'id' => $user->tenant->id,
                'nome' => $user->tenant->nome,
                'slug' => $user->tenant->slug,
            ] : null,
            ...$this->entitlement->sessionPayload($user),
        ]);
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', Rule::in(['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'])],
        ]);
        $user = $request->user();
        $profile = is_array($user->registration_profile) ? $user->registration_profile : [];
        $profile['location'] = ['city' => trim($data['city']), 'state' => $data['state']];
        $user->forceFill(['registration_profile' => $profile])->save();

        return response()->json(['message' => 'Localização de trabalho atualizada.', 'location' => $profile['location']]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:/^\d{10,11}$/'],
            'preferences' => ['sometimes', 'array:email_notifications,product_updates'],
            'preferences.email_notifications' => ['sometimes', 'boolean'],
            'preferences.product_updates' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $profile = is_array($user->registration_profile) ? $user->registration_profile : [];
        $profile['phone'] = $data['phone'] ?? null;
        $profile['preferences'] = array_merge(
            ['email_notifications' => true, 'product_updates' => true],
            is_array($profile['preferences'] ?? null) ? $profile['preferences'] : [],
            $data['preferences'] ?? [],
        );
        $user->forceFill(['name' => trim($data['name']), 'registration_profile' => $profile])->save();

        return response()->json(['message' => 'Perfil atualizado.', 'user' => [
            'name' => $user->name,
            'email' => $user->email,
            'registration_profile' => $user->registration_profile,
        ]]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:72'],
            'password' => ['required', 'confirmed', Password::min(12), 'max:72'],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'A senha atual não confere.']);
        }
        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();

        return response()->json(['message' => 'Senha alterada.']);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $data = $request->validate(['avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=80,min_height=80,max_width=3000,max_height=3000']]);
        $directory = public_path('uploads/avatars');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $name = Str::uuid().'.'.$data['avatar']->extension();
        $data['avatar']->move($directory, $name);

        $user = $request->user();
        $profile = is_array($user->registration_profile) ? $user->registration_profile : [];
        $old = $profile['avatar_url'] ?? null;
        $profile['avatar_url'] = '/uploads/avatars/'.$name;
        $user->forceFill(['registration_profile' => $profile])->save();
        if (is_string($old) && preg_match('#^/uploads/avatars/[a-f0-9-]+\.(?:jpe?g|png|webp)$#i', $old)) {
            $oldPath = public_path(ltrim($old, '/'));
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        return response()->json(['message' => 'Foto atualizada.', 'avatar_url' => $profile['avatar_url']]);
    }

    public function switchProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string', 'max:40'],
        ]);
        $slug = app(ProductContext::class)->normalize($data['product']);
        abort_if(! $slug, 422, 'Produto inválido.');
        abort_unless($this->entitlement->canAccessProduct($request->user(), $slug), 403, 'Este produto não está liberado para a conta.');
        $request->user()->forceFill(['last_product_slug' => $slug])->save();

        return response()->json(['last_product_slug' => $slug]);
    }

    public function sessions(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->authService->sessions($request->user())]);
    }

    public function revokeSession(Request $request, int $tokenId): JsonResponse
    {
        if (! $this->authService->revokeSession($request->user(), $tokenId)) {
            return response()->json(['message' => 'Sessão não encontrada'], 404);
        }

        return response()->json(['message' => 'Sessão encerrada']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAll($request->user(), $request);

        return response()->json(['message' => 'Todas as sessões foram encerradas']);
    }

    public function logoutOthers(Request $request): JsonResponse
    {
        $count = $this->authService->logoutOthers($request->user());

        return response()->json(['message' => 'Outras sessões encerradas', 'revoked' => $count]);
    }
}
