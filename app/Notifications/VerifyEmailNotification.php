<?php

namespace App\Notifications;

use App\Services\MailTemplateSettings;
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

        $templates = app(MailTemplateSettings::class);

        return $templates->mailMessage(
            MailTemplateSettings::EMAIL_VERIFICATION,
            $templates->userVariables($notifiable, [
                'verification_url' => $url,
                'expires_in' => '60 минут',
            ]),
            $url,
        );
    }
}
