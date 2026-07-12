<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        return (new MailMessage)
            ->subject('Подтверждение email — '.site_name())
            ->greeting('Подтверждение регистрации')
            ->line('Вы зарегистрировали учётную запись на сайте '.site_name().'.')
            ->line('Нажмите кнопку ниже, чтобы подтвердить адрес электронной почты.')
            ->action('Подтвердить email', $url)
            ->line('Ссылка действительна 60 минут. Если вы не регистрировались, просто проигнорируйте письмо.');
    }
}
