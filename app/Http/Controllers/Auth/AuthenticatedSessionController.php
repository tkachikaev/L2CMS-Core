<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('theme::auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $login = Str::lower(trim((string) $request->validated()['login']));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'name';

        if (! Auth::attempt([
            $field => $login,
            'password' => (string) $request->validated()['password'],
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'login' => 'Неверный логин, email или пароль.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }
}
