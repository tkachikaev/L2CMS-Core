<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('admin');
        $administrator = $guard->user();

        if ($administrator === null) {
            return redirect()->guest(route('admin.login'));
        }

        if (! $administrator->is_active) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->with('status', __('The administrator account was disabled.'));
        }

        return $next($request);
    }
}
