<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use App\Services\RegistrationVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $this->authService->register($request->validated());
        $delivery = app(RegistrationVerificationService::class)->send($data['user']);

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

        return response()->json([
            'user' => [
                'id' => $result['user']->id,
                'tenant_id' => $result['user']->tenant_id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
            ],
            'token' => $result['token'],
            'requires_email_verification' => $result['user']->requires_email_verification && ! $result['user']->hasVerifiedEmail(),
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
            'tenant' => $user->tenant ? [
                'id' => $user->tenant->id,
                'nome' => $user->tenant->nome,
                'slug' => $user->tenant->slug,
            ] : null,
        ]);
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
}
