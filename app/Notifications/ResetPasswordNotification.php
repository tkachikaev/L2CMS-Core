<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Восстановление пароля — '.site_name())
            ->greeting('Восстановление пароля')
            ->line('Мы получили запрос на изменение пароля вашей учётной записи.')
            ->action('Изменить пароль', $url)
            ->line('Ссылка действительна 60 минут. Если вы не запрашивали восстановление, проигнорируйте письмо.');
    }
}
