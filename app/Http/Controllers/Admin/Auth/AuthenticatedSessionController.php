<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminLoginLog;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $throttleKey = $this->throttleKey($email, $request->ip());
        $maxAttempts = config('cms.admin.login_max_attempts', 5);
        $decaySeconds = config('cms.admin.login_decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->writeLoginLog($request, $email, false, 'throttled');
            $auditLogger->failed(
                category: 'admin',
                action: 'auth.login_failed',
                actor: $email,
                target: 'Панель управления',
                details: ['reason' => 'throttled'],
                actorType: 'admin',
            );

            throw ValidationException::withMessages([
                'email' => "Слишком много попыток входа. Повторите через {$seconds} сек.",
            ]);
        }

        $credentials = [
            'email' => $email,
            'password' => $validated['password'],
            'is_active' => true,
        ];

        if (! Auth::guard('admin')->attempt($credentials, (bool) ($validated['remember'] ?? false))) {
            RateLimiter::hit($throttleKey, $decaySeconds);

            $admin = Admin::query()->where('email', $email)->first();
            $reason = $admin && ! $admin->is_active ? 'inactive' : 'invalid_credentials';
            $this->writeLoginLog($request, $email, false, $reason, $admin);
            $auditLogger->failed(
                category: 'admin',
                action: 'auth.login_failed',
                actor: $admin ?? $email,
                target: 'Панель управления',
                details: ['reason' => $reason],
                actorType: $admin === null ? 'admin' : null,
            );

            throw ValidationException::withMessages([
                'email' => 'Неверный адрес электронной почты или пароль.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->writeLoginLog($request, $email, true, null, $admin);
        $auditLogger->success(
            category: 'admin',
            action: 'auth.login',
            actor: $admin,
            target: 'Панель управления',
        );

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if ($admin !== null) {
            $auditLogger->success(
                category: 'admin',
                action: 'auth.logout',
                actor: $admin,
                target: 'Панель управления',
            );
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'Вы вышли из панели управления.');
    }

    private function throttleKey(string $email, ?string $ip): string
    {
        return 'admin-login:'.hash('sha256', $email.'|'.($ip ?? 'unknown'));
    }

    private function writeLoginLog(
        Request $request,
        string $email,
        bool $successful,
        ?string $failureReason,
        ?Admin $admin = null,
    ): void {
        AdminLoginLog::query()->create([
            'admin_id' => $admin?->id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'successful' => $successful,
            'failure_reason' => $failureReason,
        ]);
    }
}
