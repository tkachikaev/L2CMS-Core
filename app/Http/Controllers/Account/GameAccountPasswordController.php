<?php

namespace App\Http\Controllers\Account;

use App\Contracts\GameAccountGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangeGameAccountPasswordRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class GameAccountPasswordController extends Controller
{
    public function __construct(
        private readonly GameAccountGateway $gateway,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function update(ChangeGameAccountPasswordRequest $request): RedirectResponse
    {
        $gameAccount = $this->gameAccountId($request);
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $account = $user->gameAccounts()->with('loginServer')->findOrFail($gameAccount);
        if (! Hash::check((string) $request->validated('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => __('The current CMS password is incorrect.')]);
        }

        if (mb_strtolower((string) $request->validated('game_password')) === mb_strtolower($account->game_login)) {
            return back()->withErrors(['game_password' => __('The game password must not match the game login.')]);
        }

        try {
            if (! $this->gateway->changePassword(
                $account->loginServer,
                $account->game_login,
                (string) $request->validated('game_password'),
            )) {
                return back()->withErrors(['game_password' => __('The game account was not found on the LoginServer.')]);
            }

            $this->auditLogger->success(
                category: 'game_account',
                action: 'user.game_account_password_changed',
                actor: $user,
                target: $account,
                details: ['login_server_id' => $account->login_server_id, 'game_login' => $account->game_login],
            );

            return back()->with('status', __('Game account password changed.'));
        } catch (Throwable $exception) {
            Log::warning('Game account password change failed.', [
                'exception' => $exception::class,
                'login_server_id' => $account->login_server_id,
            ]);
            $this->auditLogger->failed(
                category: 'game_account',
                action: 'user.game_account_password_change_failed',
                actor: $user,
                target: $account,
                details: ['exception_class' => $exception::class],
            );

            return back()->withErrors(['game_password' => __('The game password could not be changed. Try again later.')]);
        }
    }

    private function gameAccountId(ChangeGameAccountPasswordRequest $request): int
    {
        $value = $request->route('gameAccount');

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        abort(404);
    }
}
