<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('theme::auth.forgot-password');
    }

    public function store(Request $request, MailSettings $mailSettings): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ], [
            'email.required' => 'Укажите email.',
            'email.email' => 'Email указан неверно.',
        ]);

        if (! $mailSettings->isReady()) {
            return back()->withErrors([
                'email' => 'Восстановление пароля временно недоступно. Обратитесь к администрации сайта.',
            ]);
        }

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (Throwable $exception) {
            Log::warning('Unable to send password reset link.', [
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'email' => 'Письмо отправить не удалось. Повторите попытку позже.',
            ]);
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors([
                'email' => 'Запрос уже отправлялся недавно. Повторите попытку позже.',
            ]);
        }

        return back()->with('status', 'Если этот email зарегистрирован, на него отправлена ссылка восстановления пароля.');
    }
}
