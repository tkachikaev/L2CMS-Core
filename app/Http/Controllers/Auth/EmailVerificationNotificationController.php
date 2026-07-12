<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MailSettings;
use App\Services\RegistrationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    public function store(
        Request $request,
        RegistrationSettings $registrationSettings,
        MailSettings $mailSettings,
    ): RedirectResponse {
        if (! $registrationSettings->emailVerificationRequired()) {
            return redirect()->route('account');
        }

        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('account');
        }

        if (! $mailSettings->isReady()) {
            return back()->withErrors([
                'email' => 'Отправка почты временно недоступна. Обратитесь к администрации сайта.',
            ]);
        }

        try {
            $request->user()?->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::warning('Unable to resend email verification notification.', [
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'email' => 'Письмо отправить не удалось. Повторите попытку позже.',
            ]);
        }

        return back()->with('status', 'Новая ссылка подтверждения отправлена на ваш email.');
    }
}
