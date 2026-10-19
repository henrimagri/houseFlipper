<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyAccountEmail extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $path = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification()),
        ], absolute: false);
        $url = rtrim(config('app.frontend_url'), '/').'/confirmar-email?verification='.rawurlencode($path);

        return (new MailMessage)->subject('Confirme seu e-mail no FlipControl')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Confirme seu endereço de e-mail para acessar sua conta.')
            ->action('Confirmar meu e-mail', $url)
            ->line('Este link expira em 60 minutos. Se você não criou esta conta, ignore esta mensagem.')
            ->salutation('Equipe FlipControl');
    }
}
