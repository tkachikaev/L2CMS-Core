<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveLoginServerRequest;
use App\Models\LoginServer;
use App\Services\AuditLogger;
use App\Services\Servers\ServerConnectionTester;
use App\Services\Servers\ServerDriverRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoginServerController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(ServerDriverRegistry $drivers): View
    {
        return view('admin.settings.login-server', [
            'servers' => LoginServer::query()->withCount(['gameServers', 'userGameAccounts'])->orderBy('id')->get(),
            'drivers' => $drivers->loginDrivers(),
            'report' => session('database_connection_report'),
        ]);
    }

    public function store(
        SaveLoginServerRequest $request,
        ServerConnectionTester $tester,
    ): RedirectResponse {
        $validated = $request->validated();

        if ($validated['connection_action'] === 'test') {
            $report = $tester->testLoginValues($validated);
            $this->auditConnectionTest($report, (string) $validated['name']);

            return back()
                ->withInput($request->except('database_password'))
                ->with('database_connection_report', $report + ['context' => 'login-create']);
        }

        $server = LoginServer::query()->create($this->values($validated));

        $this->auditLogger->success(
            category: 'admin',
            action: 'login_server.created',
            target: $server,
            details: ['values' => $this->auditValues($server)],
        );

        return redirect()
            ->route('admin.settings.login-server')
            ->with('status', __('LoginServer added.'));
    }

    public function update(
        SaveLoginServerRequest $request,
        LoginServer $loginServer,
        ServerConnectionTester $tester,
    ): RedirectResponse {
        $validated = $request->validated();
        $password = (string) ($validated['database_password'] ?? '');
        if ($password === '') {
            $validated['database_password'] = $loginServer->databasePassword() ?? '';
        }

        if ($validated['connection_action'] === 'test') {
            $report = $tester->testLoginValues($validated);
            $this->auditConnectionTest($report, $loginServer->name, $loginServer);

            return back()
                ->withInput($request->except('database_password'))
                ->with('database_connection_report', $report + ['context' => 'login-'.$loginServer->id]);
        }

        $before = $this->auditValues($loginServer);
        $values = $this->values($validated);
        if ($password === '') {
            unset($values['database_password']);
        }
        $loginServer->update($values);
        $loginServer->refresh();

        $this->auditLogger->success(
            category: 'admin',
            action: 'login_server.updated',
            target: $loginServer,
            details: [
                'before' => $before,
                'after' => $this->auditValues($loginServer),
            ],
        );

        return redirect()
            ->route('admin.settings.login-server')
            ->with('status', __('LoginServer settings saved.'));
    }

    public function destroy(LoginServer $loginServer): RedirectResponse
    {
        try {
            /** @var array{name:string,values:array<string,mixed>}|null $deleted */
            $deleted = DB::transaction(function () use ($loginServer): ?array {
                $lockedServer = LoginServer::query()
                    ->lockForUpdate()
                    ->findOrFail($loginServer->id);

                if ($lockedServer->gameServers()->exists() || $lockedServer->userGameAccounts()->exists()) {
                    return null;
                }

                $result = [
                    'name' => $lockedServer->name,
                    'values' => $this->auditValues($lockedServer),
                ];
                $lockedServer->delete();

                return $result;
            });
        } catch (QueryException $exception) {
            if (! $this->isIntegrityConstraintViolation($exception)) {
                throw $exception;
            }

            $deleted = null;
        }

        if ($deleted === null) {
            return back()->withErrors([
                'login_server' => __('The LoginServer is used by game servers or player accounts and cannot be deleted.'),
            ]);
        }

        $this->auditLogger->success(
            category: 'admin',
            action: 'login_server.deleted',
            target: $deleted['name'],
            details: ['values' => $deleted['values']],
        );

        return redirect()
            ->route('admin.settings.login-server')
            ->with('status', __('LoginServer :name deleted.', ['name' => $deleted['name']]));
    }

    private function isIntegrityConstraintViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        return str_starts_with($code, '23') || in_array($code, ['19', '1451'], true);
    }

    /** @param array<string,mixed> $validated @return array<string,mixed> */
    private function values(array $validated): array
    {
        return [
            'name' => (string) $validated['name'],
            'driver' => (string) $validated['driver'],
            'database_host' => (string) $validated['database_host'],
            'database_port' => (int) $validated['database_port'],
            'database_name' => (string) $validated['database_name'],
            'database_username' => (string) $validated['database_username'],
            'database_password' => trim((string) ($validated['database_password'] ?? '')) !== ''
                ? (string) $validated['database_password']
                : null,
            'database_charset' => (string) $validated['database_charset'],
        ];
    }

    /** @return array<string,mixed> */
    private function auditValues(LoginServer $server): array
    {
        return [
            'name' => $server->name,
            'driver' => $server->driver,
            'database_host' => $server->database_host,
            'database_port' => $server->database_port,
            'database_name' => $server->database_name,
            'database_username' => $server->database_username,
            'database_charset' => $server->database_charset,
            'database_password_saved' => $server->hasDatabasePassword(),
        ];
    }

    /** @param array<string,mixed> $report */
    private function auditConnectionTest(array $report, string $name, ?LoginServer $server = null): void
    {
        $details = [
            'driver' => $report['driver'] ?? null,
            'connected' => $report['connected'] ?? false,
            'compatible' => $report['compatible'] ?? null,
        ];

        if ($report['connected'] ?? false) {
            $this->auditLogger->success(
                category: 'admin',
                action: 'login_server.connection_tested',
                target: $server ?? $name,
                details: $details,
            );

            return;
        }

        $this->auditLogger->failed(
            category: 'admin',
            action: 'login_server.connection_tested',
            target: $server ?? $name,
            details: $details,
        );
    }
}
