<?php

namespace App\Livewire\Admin;

use App\Models\LoginServer;
use App\Services\AuditLogger;
use App\Services\Servers\ServerConnectionTester;
use App\Services\Servers\ServerDriverRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LoginServerManager extends Component
{
    public bool $drawerOpen = false;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $confirmingDeleteId = null;

    public string $name = '';

    public string $driver = 'l2j_mobius';

    public string $databaseHost = '127.0.0.1';

    public string $databasePort = '3306';

    public string $databaseName = '';

    public string $databaseUsername = '';

    public string $databasePassword = '';

    public string $databaseCharset = 'utf8mb4';

    /** @var array<string,mixed>|null */
    public ?array $connectionReport = null;

    public ?string $status = null;

    public bool $showChecks = false;

    /** @var array<int,array{state:string,message:string}> */
    public array $cardTestResults = [];

    public function mount(): void
    {
        $this->ensureAuthorized();
    }

    public function create(): void
    {
        $this->ensureAuthorized();
        $this->resetValidation();
        $this->resetForm();
        $this->drawerOpen = true;
    }

    public function edit(int $serverId): void
    {
        $this->ensureAuthorized();
        $server = LoginServer::query()->findOrFail($serverId);

        $this->resetValidation();
        $this->editingId = $server->id;
        $this->name = $server->name;
        $this->driver = $server->driver;
        $this->databaseHost = $server->database_host;
        $this->databasePort = (string) $server->database_port;
        $this->databaseName = $server->database_name;
        $this->databaseUsername = $server->database_username;
        $this->databasePassword = '';
        $this->databaseCharset = $server->database_charset;
        $this->connectionReport = null;
        $this->status = null;
        $this->showChecks = false;
        $this->confirmingDeleteId = null;
        $this->drawerOpen = true;
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->connectionReport = null;
        $this->showChecks = false;
        $this->confirmingDeleteId = null;
        $this->resetValidation();
    }

    public function testStored(int $serverId): void
    {
        $this->ensureAuthorized();
        $server = LoginServer::query()->findOrFail($serverId);
        $report = app(ServerConnectionTester::class)->testLoginServer($server);

        $this->auditConnectionTest($report, $server->name, $server);
        $this->cardTestResults[$server->id] = $this->cardTestResult($report);
    }

    public function testConnection(): void
    {
        $this->ensureAuthorized();
        $validated = $this->validate($this->rules(), [], $this->attributes());
        $values = $this->connectionValues($validated);
        $server = $this->editingId !== null
            ? LoginServer::query()->findOrFail($this->editingId)
            : null;

        if ($values['database_password'] === '' && $server instanceof LoginServer) {
            $values['database_password'] = $server->databasePassword() ?? '';
        }

        $report = app(ServerConnectionTester::class)->testLoginValues($values);
        $this->auditConnectionTest($report, $this->name, $server);

        $this->connectionReport = $report;
        $this->showChecks = false;
        $this->status = null;
        $this->drawerOpen = true;
    }

    public function save(): void
    {
        $this->ensureAuthorized();
        $validated = $this->validate($this->rules(), [], $this->attributes());
        $values = $this->connectionValues($validated);
        $audit = app(AuditLogger::class);

        if ($this->editingId === null) {
            $values['database_password'] = $values['database_password'] !== ''
                ? $values['database_password']
                : null;
            $server = LoginServer::query()->create($values);

            $audit->success(
                category: 'admin',
                action: 'login_server.created',
                target: $server,
                details: ['values' => $this->auditValues($server)],
            );

            $this->editingId = $server->id;
            $this->status = __('LoginServer added.');
        } else {
            $server = LoginServer::query()->findOrFail($this->editingId);
            $before = $this->auditValues($server);

            if ($values['database_password'] === '') {
                unset($values['database_password']);
            }

            $server->update($values);
            $server->refresh();

            $audit->success(
                category: 'admin',
                action: 'login_server.updated',
                target: $server,
                details: [
                    'before' => $before,
                    'after' => $this->auditValues($server),
                ],
            );

            $this->status = __('LoginServer settings saved.');
        }

        unset($this->cardTestResults[$server->id]);
        $this->databasePassword = '';
        $this->connectionReport = null;
        $this->confirmingDeleteId = null;
    }

    public function confirmDelete(int $serverId): void
    {
        $this->ensureAuthorized();
        $this->confirmingDeleteId = $serverId;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $this->ensureAuthorized();

        if ($this->confirmingDeleteId === null) {
            return;
        }

        $serverId = $this->confirmingDeleteId;

        try {
            /** @var array{name:string,values:array<string,mixed>}|null $deleted */
            $deleted = DB::transaction(function () use ($serverId): ?array {
                $server = LoginServer::query()
                    ->lockForUpdate()
                    ->findOrFail($serverId);

                if ($server->gameServers()->exists() || $server->userGameAccounts()->exists()) {
                    return null;
                }

                $result = [
                    'name' => $server->name,
                    'values' => $this->auditValues($server),
                ];
                $server->delete();

                return $result;
            });
        } catch (QueryException $exception) {
            if (! $this->isIntegrityConstraintViolation($exception)) {
                throw $exception;
            }

            $deleted = null;
        }

        if ($deleted === null) {
            $this->addError('loginServer', __('The LoginServer is used by game servers or player accounts and cannot be deleted.'));
            $this->confirmingDeleteId = null;

            return;
        }

        app(AuditLogger::class)->success(
            category: 'admin',
            action: 'login_server.deleted',
            target: $deleted['name'],
            details: ['values' => $deleted['values']],
        );

        if ($this->editingId === $serverId) {
            $this->closeDrawer();
            $this->resetForm();
        }

        unset($this->cardTestResults[$serverId]);
        $this->status = __('LoginServer :name deleted.', ['name' => $deleted['name']]);
        $this->confirmingDeleteId = null;
    }

    public function render(): View
    {
        return view('livewire.admin.login-server-manager', [
            'servers' => LoginServer::query()
                ->withCount(['gameServers', 'userGameAccounts'])
                ->orderBy('id')
                ->get(),
            'drivers' => app(ServerDriverRegistry::class)->loginDrivers(),
        ]);
    }

    /** @return array<string,mixed> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['required', Rule::in(app(ServerDriverRegistry::class)->loginDriverKeys())],
            'databaseHost' => ['required', 'string', 'max:255'],
            'databasePort' => ['required', 'integer', 'between:1,65535'],
            'databaseName' => ['required', 'string', 'max:64'],
            'databaseUsername' => ['required', 'string', 'max:128'],
            'databasePassword' => ['nullable', 'string', 'max:1024'],
            'databaseCharset' => ['required', Rule::in(['utf8mb4', 'utf8', 'latin1', 'cp1251'])],
        ];
    }

    /** @return array<string,string> */
    private function attributes(): array
    {
        return [
            'name' => __('LoginServer name'),
            'driver' => __('LoginServer driver'),
            'databaseHost' => __('Database host'),
            'databasePort' => __('Database port'),
            'databaseName' => __('Database name'),
            'databaseUsername' => __('Database username'),
            'databasePassword' => __('Database password'),
            'databaseCharset' => __('Database charset'),
        ];
    }

    /** @param array<string,mixed> $validated @return array<string,mixed> */
    private function connectionValues(array $validated): array
    {
        return [
            'name' => trim((string) $validated['name']),
            'driver' => trim((string) $validated['driver']),
            'database_host' => trim((string) $validated['databaseHost']),
            'database_port' => (int) $validated['databasePort'],
            'database_name' => trim((string) $validated['databaseName']),
            'database_username' => trim((string) $validated['databaseUsername']),
            'database_password' => (string) ($validated['databasePassword'] ?? ''),
            'database_charset' => trim((string) $validated['databaseCharset']),
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

    /** @param array<string,mixed> $report @return array{state:string,message:string} */
    private function cardTestResult(array $report): array
    {
        if (! ($report['connected'] ?? false)) {
            return ['state' => 'failed', 'message' => __('Database connection failed.')];
        }

        if (($report['driver_ready'] ?? true) && ($report['compatible'] ?? null) === false) {
            return [
                'state' => 'failed',
                'message' => __('The database is reachable, but required driver tables or columns are missing.'),
            ];
        }

        return ['state' => 'success', 'message' => __('Database connection established.')];
    }

    /** @param array<string,mixed> $report */
    private function auditConnectionTest(array $report, string $name, ?LoginServer $server): void
    {
        $details = [
            'driver' => $report['driver'] ?? null,
            'connected' => $report['connected'] ?? false,
            'compatible' => $report['compatible'] ?? null,
        ];
        $audit = app(AuditLogger::class);

        if ($report['connected'] ?? false) {
            $audit->success(
                category: 'admin',
                action: 'login_server.connection_tested',
                target: $server ?? $name,
                details: $details,
            );

            return;
        }

        $audit->failed(
            category: 'admin',
            action: 'login_server.connection_tested',
            target: $server ?? $name,
            details: $details,
        );
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->confirmingDeleteId = null;
        $this->name = '';
        $this->driver = 'l2j_mobius';
        $this->databaseHost = '127.0.0.1';
        $this->databasePort = '3306';
        $this->databaseName = '';
        $this->databaseUsername = '';
        $this->databasePassword = '';
        $this->databaseCharset = 'utf8mb4';
        $this->connectionReport = null;
        $this->status = null;
        $this->showChecks = false;
    }

    private function ensureAuthorized(): void
    {
        abort_unless(auth('admin')->check(), 403);
    }

    private function isIntegrityConstraintViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        return str_starts_with($code, '23') || in_array($code, ['19', '1451'], true);
    }
}
