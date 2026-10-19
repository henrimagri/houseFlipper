<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\PasswordChangedEmail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function requestLink(ForgotPasswordRequest $request): JsonResponse
    {
        $user = $this->eligibleUser($request->string('email')->toString());

        if ($user) {
            try {
                Password::broker()->sendResetLink(['email' => $user->email]);
            } catch (\Throwable $exception) {
                Log::warning('Falha ao enviar redefinição de senha', [
                    'user_id' => $user->id,
                    'exception_type' => $exception::class,
                ]);
            }
        }

        return response()->json([
            'message' => 'Se a conta estiver apta, enviaremos as instruções de redefinição.',
        ], 202);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->eligibleUser($data['email']);
        if (! $user) {
            return $this->invalidToken();
        }

        $completed = false;
        $status = Password::broker()->reset(
            $data,
            function (User $resetUser, string $password) use (&$completed): void {
                if (! $this->isEligible($resetUser)) {
                    return;
                }

                $resetUser->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
                $resetUser->tokens()->delete();
                event(new PasswordReset($resetUser));
                $completed = true;
            },
        );

        if ($status !== Password::PASSWORD_RESET || ! $completed || ! $user->refresh()->tokens()->doesntExist()) {
            return $this->invalidToken();
        }

        try {
            $user->notify(new PasswordChangedEmail);
        } catch (\Throwable $exception) {
            Log::warning('Falha ao enviar alerta de senha alterada', [
                'user_id' => $user->id,
                'exception_type' => $exception::class,
            ]);
        }

        return response()->json(['message' => 'Senha redefinida. Entre novamente em todos os dispositivos.']);
    }

    private function eligibleUser(string $email): ?User
    {
        $matches = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->where('ativo_flg', true)->where('deletado_flg', false)->limit(2)->get();
        if ($matches->count() !== 1) {
            return null;
        }

        $user = $matches->first();

        return $this->isEligible($user) ? $user : null;
    }

    private function isEligible(User $user): bool
    {
        return $user->ativo_flg
            && ! $user->deletado_flg
            && $user->tenant_id !== null
            && $user->tenant !== null
            && $user->tenant->ativo_flg
            && ! $user->tenant->deletado_flg;
    }

    private function invalidToken(): JsonResponse
    {
        return response()->json([
            'message' => 'O link é inválido ou expirou. Solicite uma nova redefinição.',
            'errors' => ['token' => ['O link é inválido ou expirou.']],
        ], 422);
    }
}
