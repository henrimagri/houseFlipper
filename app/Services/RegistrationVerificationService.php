<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyAccountEmail;
use Illuminate\Support\Facades\Log;

class RegistrationVerificationService
{
    public function send(User $user): string
    {
        try {
            $user->notify(new VerifyAccountEmail);

            return in_array(config('mail.default'), ['array', 'log'], true) ? 'local' : 'sent';
        } catch (\Throwable $exception) {
            Log::warning('Falha ao enviar confirmação de cadastro', ['user_id' => $user->id, 'exception_type' => $exception::class]);

            return 'failed';
        }
    }
}
