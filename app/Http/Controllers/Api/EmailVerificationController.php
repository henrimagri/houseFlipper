<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RegistrationVerificationService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        abort_if(! $user->ativo_flg || $user->deletado_flg || ($user->tenant_id && (! $user->tenant || ! $user->tenant->ativo_flg || $user->tenant->deletado_flg)), 403);
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json(['message' => 'E-mail confirmado. Você já pode acessar sua conta.']);
    }

    public function resend(Request $request, RegistrationVerificationService $verification): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Seu e-mail já foi confirmado.', 'delivery' => 'verified']);
        }
        $delivery = $verification->send($request->user());

        return response()->json(['delivery' => $delivery, 'message' => match ($delivery) {
            'local' => 'Confirmação gerada no ambiente local. O envio externo ainda não está configurado.',
            'sent' => 'Pedido de envio aceito. Confira sua caixa de entrada e a pasta de spam.',
            default => 'Não foi possível enviar a confirmação. Tente novamente mais tarde.',
        }], $delivery === 'failed' ? 503 : 202);
    }
}
