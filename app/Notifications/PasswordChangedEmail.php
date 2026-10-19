<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedEmail extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Sua senha do FlipControl foi alterada')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('A senha da sua conta foi redefinida e todas as sessões anteriores foram encerradas.')
            ->line('Se você não realizou essa alteração, solicite uma nova redefinição imediatamente e contate o suporte.')
            ->salutation('Equipe FlipControl');
    }
}
