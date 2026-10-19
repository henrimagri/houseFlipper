<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordEmail extends Notification
{
    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fragment = http_build_query(['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], '', '&', PHP_QUERY_RFC3986);
        $url = rtrim(config('app.frontend_url'), '/').'/redefinir-senha#'.$fragment;
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)->subject('Redefina sua senha no FlipControl')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta.')
            ->action('Redefinir minha senha', $url)
            ->line("Este link expira em {$minutes} minutos e pode ser usado uma única vez.")
            ->line('Se você não solicitou a alteração, ignore esta mensagem. Sua senha continuará a mesma.')
            ->salutation('Equipe FlipControl');
    }
}
