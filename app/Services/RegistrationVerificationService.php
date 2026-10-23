<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VerifyAccountEmail;
use App\Services\Platform\EmailDeliveryTelemetry;
use Illuminate\Support\Facades\Log;

class RegistrationVerificationService
{
    public function send(User $user): string
    {
        $previous = request()->attributes->get('platform_email_recipient');
        request()->attributes->set('platform_email_recipient', $user);
        try {
            $user->notify(new VerifyAccountEmail);

            return in_array(config('mail.default'), ['array', 'log'], true) ? 'local' : 'sent';
        } catch (\Throwable $exception) {
            EmailDeliveryTelemetry::failed();
            Log::warning('Falha ao enviar confirmação de cadastro', ['user_id' => $user->id, 'exception_type' => $exception::class]);

            return 'failed';
        } finally {
            if ($previous) {
                request()->attributes->set('platform_email_recipient', $previous);
            } else {
                request()->attributes->remove('platform_email_recipient');
            }
        }
    }
}
