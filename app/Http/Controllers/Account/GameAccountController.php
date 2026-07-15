<?php

namespace App\Http\Controllers\Account;

use App\Contracts\GameAccountGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CreateGameAccountRequest;
use App\Models\GameServer;
use App\Models\LoginServer;
use App\Models\User;
use App\Models\UserGameAccount;
use App\Services\AuditLogger;
use App\Services\GameAccounts\InterludeClassNames;
use App\Services\GameAccountSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class GameAccountController extends Controller
{
    public function __construct(
        private readonly GameAccountGateway $gateway,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(Request $request, GameAccountSettings $settings): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $values = $settings->values();
        if (! $values['enabled']) {
            return redirect()->to(public_route('account'))->with('warning', __('Creating game accounts is disabled.'));
        }

        if ($user->gameAccounts()->count() >= $values['max_accounts']) {
            return redirect()->to(public_route('account'))->with('warning', __('You have reached the game account limit.'));
        }

        return view('account.game-accounts.create', [
            'user' => $user,
            'settings' => $values,
            'gameServers' => $this->availableGameServers(),
        ]);
    }

    public function store(CreateGameAccountRequest $request, GameAccountSettings $settings): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $values = $settings->values();
        if (! $values['enabled']) {
            return back()->withErrors(['game_login' => __('Creating game accounts is disabled.')]);
        }

        $gameServer = $this->availableGameServers()->firstWhere('id', $request->integer('game_server_id'));
        if (! $gameServer instanceof GameServer || ! $gameServer->loginServer instanceof LoginServer) {
            return back()->withInput($request->except(['game_password', 'game_password_confirmation']))
                ->withErrors(['game_server_id' => __('The selected game server is unavailable.')]);
        }

        $loginServer = $gameServer->loginServer;
        $login = trim((string) $request->validated('game_login'));
        $normalized = Str::lower($login);

        if ($user->gameAccounts()->count() >= $values['max_accounts']) {
            return back()->withErrors(['game_login' => __('You have reached the game account limit.')]);
        }

        if (UserGameAccount::query()
            ->where('login_server_id', $loginServer->id)
            ->where('normalized_login', $normalized)
            ->exists()) {
            return back()->withInput($request->except(['game_password', 'game_password_confirmation']))
                ->withErrors(['game_login' => __('This game login is already linked to a CMS account.')]);
        }

        try {
            if ($this->gateway->accountExists($loginServer, $login)) {
                return back()->withInput($request->except(['game_password', 'game_password_confirmation']))
                    ->withErrors(['game_login' => __('This game login already exists.')]);
            }

            $link = DB::transaction(function () use ($user, $loginServer, $gameServer, $login, $normalized, $values): UserGameAccount {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($lockedUser->gameAccounts()->count() >= $values['max_accounts']) {
                    throw new RuntimeException('game_account_limit_reached');
                }

                return UserGameAccount::query()->create([
                    'user_id' => $lockedUser->id,
                    'login_server_id' => $loginServer->id,
                    'registration_game_server_id' => $gameServer->id,
                    'game_login' => $login,
                    'normalized_login' => $normalized,
                    'created_via_cms' => true,
                ]);
            });

            try {
                $this->gateway->createAccount(
                    $loginServer,
                    $login,
                    (string) $request->validated('game_password'),
                    $user->email,
                );
            } catch (Throwable $exception) {
                $link->delete();
                throw $exception;
            }

            $this->auditLogger->success(
                category: 'game_account',
                action: 'user.game_account_created',
                actor: $user,
                target: $link,
                details: [
                    'login_server_id' => $loginServer->id,
                    'game_server_id' => $gameServer->id,
                    'game_login' => $login,
                ],
            );

            return redirect()->to(public_route('game-accounts.show', ['gameAccount' => $link]))
                ->with('status', __('Game account created.'));
        } catch (Throwable $exception) {
            Log::warning('Game account creation failed.', [
                'exception' => $exception::class,
                'login_server_id' => $loginServer->id,
            ]);
            $this->auditLogger->failed(
                category: 'game_account',
                action: 'user.game_account_creation_failed',
                actor: $user,
                target: $login,
                details: ['login_server_id' => $loginServer->id, 'exception_class' => $exception::class],
            );

            return back()->withInput($request->except(['game_password', 'game_password_confirmation']))
                ->withErrors(['game_login' => __('The game account could not be created. Check the server connection or try again later.')]);
        }
    }

    public function show(Request $request, InterludeClassNames $classNames): View
    {
        $gameAccount = $this->gameAccountId($request);
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $account = $user->gameAccounts()
            ->with(['loginServer.gameServers.translations', 'registrationGameServer.translations'])
            ->findOrFail($gameAccount);
        $summary = null;
        $summaryUnavailable = false;

        try {
            $summary = $this->gateway->accountSummary($account->loginServer, $account->game_login);
        } catch (Throwable $exception) {
            $summaryUnavailable = true;
            Log::warning('Game account summary loading failed.', [
                'exception' => $exception::class,
                'login_server_id' => $account->login_server_id,
            ]);
        }

        $worlds = [];
        foreach ($account->loginServer->gameServers as $gameServer) {
            if (! $gameServer->connectionConfigured() || ! $this->gateway->supportsGameServer($gameServer)) {
                continue;
            }

            try {
                $characters = array_map(static fn (array $character): array => $character + [
                    'class_name' => $classNames->name($character['class_id']),
                ], $this->gateway->characters($gameServer, $account->game_login));
                $worlds[] = ['server' => $gameServer, 'characters' => $characters, 'available' => true];
            } catch (Throwable $exception) {
                Log::warning('Game characters loading failed.', [
                    'exception' => $exception::class,
                    'game_server_id' => $gameServer->id,
                ]);
                $worlds[] = ['server' => $gameServer, 'characters' => [], 'available' => false];
            }
        }

        return view('account.game-accounts.show', [
            'user' => $user,
            'account' => $account,
            'summary' => $summary,
            'summaryUnavailable' => $summaryUnavailable,
            'worlds' => $worlds,
        ]);
    }

    private function gameAccountId(Request $request): int
    {
        $value = $request->route('gameAccount');

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        abort(404);
    }

    /** @return Collection<int, GameServer> */
    private function availableGameServers(): Collection
    {
        return GameServer::query()
            ->with(['loginServer', 'translations'])
            ->whereNotNull('login_server_id')
            ->where('driver', 'l2j_mobius_ct0_interlude')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (GameServer $server): bool => $server->connectionConfigured()
                && $server->loginServer instanceof LoginServer
                && $this->gateway->supportsLoginServer($server->loginServer));
    }
}
