<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveGameServerConnectionRequest;
use App\Models\GameServer;
use App\Models\LoginServer;
use App\Services\AuditLogger;
use App\Services\Servers\ServerConnectionTester;
use Illuminate\Http\RedirectResponse;

class GameServerConnectionController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function update(
        SaveGameServerConnectionRequest $request,
        GameServer $gameServer,
        ServerConnectionTester $tester,
    ): RedirectResponse {
        $validated = $request->validated();
        $loginServer = LoginServer::query()->findOrFail((int) $validated['login_server_id']);
        $password = (string) ($validated['database_password'] ?? '');

        if ($password === '' && ! (bool) $validated['use_login_server_connection']) {
            $validated['database_password'] = $gameServer->databasePassword() ?? '';
        }

        if ($validated['connection_action'] === 'test') {
            $report = $tester->testGameValues($validated, $loginServer);
            $this->auditConnectionTest($report, $gameServer, $loginServer);

            return back()
                ->withInput($request->except('database_password'))
                ->with('database_connection_report', $report + ['context' => 'game-'.$gameServer->id]);
        }

        $before = $this->auditValues($gameServer);
        $useLoginConnection = (bool) $validated['use_login_server_connection'];
        $values = [
            'login_server_id' => $loginServer->id,
            'driver' => (string) $validated['driver'],
            'use_login_server_connection' => $useLoginConnection,
            'database_host' => $useLoginConnection ? null : (string) $validated['database_host'],
            'database_port' => $useLoginConnection ? null : (int) $validated['database_port'],
            'database_name' => $useLoginConnection ? null : (string) $validated['database_name'],
            'database_username' => $useLoginConnection ? null : (string) $validated['database_username'],
            'database_charset' => $useLoginConnection ? null : (string) $validated['database_charset'],
        ];

        if ($useLoginConnection) {
            $values['database_password'] = null;
        } elseif ($password !== '') {
            $values['database_password'] = $password;
        }

        $gameServer->update($values);
        $gameServer->refresh();

        $this->auditLogger->success(
            category: 'admin',
            action: 'game_server.connection_updated',
            target: $gameServer,
            details: [
                'before' => $before,
                'after' => $this->auditValues($gameServer),
            ],
        );

        return redirect()
            ->route('admin.settings.game-server')
            ->with('status', __('GameServer database connection saved.'));
    }

    /** @param array<string,mixed> $report */
    private function auditConnectionTest(array $report, GameServer $gameServer, LoginServer $loginServer): void
    {
        $details = [
            'login_server_id' => $loginServer->id,
            'driver' => $report['driver'] ?? null,
            'connected' => $report['connected'] ?? false,
            'compatible' => $report['compatible'] ?? null,
        ];

        if ($report['connected'] ?? false) {
            $this->auditLogger->success(
                category: 'admin',
                action: 'game_server.connection_tested',
                target: $gameServer,
                details: $details,
            );

            return;
        }

        $this->auditLogger->failed(
            category: 'admin',
            action: 'game_server.connection_tested',
            target: $gameServer,
            details: $details,
        );
    }

    /** @return array<string,mixed> */
    private function auditValues(GameServer $server): array
    {
        return [
            'login_server_id' => $server->login_server_id,
            'driver' => $server->driver,
            'use_login_server_connection' => $server->use_login_server_connection,
            'database_host' => $server->database_host,
            'database_port' => $server->database_port,
            'database_name' => $server->database_name,
            'database_username' => $server->database_username,
            'database_charset' => $server->database_charset,
            'database_password_saved' => $server->hasDatabasePassword(),
        ];
    }
}
