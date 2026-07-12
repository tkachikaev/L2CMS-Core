<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\MailSettings;
use App\Services\RegistrationSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    public function create(RegistrationSettings $settings, MailSettings $mailSettings): View
    {
        if (! $settings->enabled()) {
            return view('theme::auth.registration-disabled', [
                'reason' => 'Администрация сайта временно закрыла создание новых учётных записей.',
            ]);
        }

        if ($settings->emailVerificationRequired() && ! $mailSettings->isReady()) {
            return view('theme::auth.registration-disabled', [
                'reason' => 'Регистрация временно недоступна, потому что отправка почты ещё не настроена.',
            ]);
        }

        return view('theme::auth.register', [
            'emailVerificationRequired' => $settings->emailVerificationRequired(),
        ]);
    }

    public function store(
        RegisterRequest $request,
        RegistrationSettings $settings,
        MailSettings $mailSettings,
    ): RedirectResponse {
        abort_unless($settings->enabled(), 403, 'Регистрация новых пользователей отключена.');
        abort_if(
            $settings->emailVerificationRequired() && ! $mailSettings->isReady(),
            503,
            'Регистрация временно недоступна: отправка почты не настроена.'
        );

        $validated = $request->validated();
        $user = User::query()->create([
            'name' => Str::lower(trim((string) $validated['name'])),
            'email' => Str::lower(trim((string) $validated['email'])),
            'password' => Hash::make((string) $validated['password']),
        ]);

        if (! $settings->emailVerificationRequired()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user);
        $request->session()->regenerate();

        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            Log::warning('Unable to send registration email.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return redirect()
                ->route('verification.notice')
                ->with('warning', 'Учётная запись создана, но письмо подтверждения отправить не удалось. Повторите отправку позже.');
        }

        if ($settings->emailVerificationRequired() && ! $user->hasVerifiedEmail()) {
            return redirect()
                ->route('verification.notice')
                ->with('status', 'Учётная запись создана. Проверьте почту и подтвердите email.');
        }

        return redirect()
            ->route('account')
            ->with('status', 'Учётная запись успешно создана.');
    }
}
