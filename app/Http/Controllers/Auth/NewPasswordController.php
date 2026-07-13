<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('theme::auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'email.required' => 'Укажите email.',
            'email.email' => 'Email указан неверно.',
            'password.required' => 'Укажите новый пароль.',
            'password.string' => 'Пароль должен быть строкой.',
            'password.min' => 'Пароль должен содержать не менее 8 символов.',
            'password.letters' => 'Пароль должен содержать хотя бы одну букву.',
            'password.numbers' => 'Пароль должен содержать хотя бы одну цифру.',
            'password.confirmed' => 'Пароли не совпадают.',
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) use ($auditLogger): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                $auditLogger->success(
                    category: 'user',
                    action: 'user.password_changed',
                    actor: $user,
                    target: $user,
                );
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Ссылка восстановления недействительна или устарела.',
            ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Пароль изменён. Теперь можно войти.');
    }
}
