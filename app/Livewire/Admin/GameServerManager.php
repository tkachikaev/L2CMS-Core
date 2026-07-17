<?php

namespace App\Livewire\Admin;

use App\Exceptions\GameServerDeletionConfirmationRequired;
use App\Models\GameServer;
use App\Models\GameServerTranslation;
use App\Models\LoginServer;
use App\Services\AuditLogger;
use App\Services\GameServerDeletionImpact;
use App\Services\GameServerSettings;
use App\Services\Localization\LanguageManager;
use App\Services\Servers\ServerConnectionTester;
use App\Services\Servers\ServerDriverRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GameServerManager extends Component
{
    public bool $drawerOpen = false;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $confirmingDeleteId = null;

    #[Locked]
    public ?string $deleteImpactFingerprint = null;

    #[Locked]
    public bool $deleteImpactRequiresConfirmation = false;

    #[Locked]
    public int $deleteImpactAccountCount = 0;

    #[Locked]
    public ?string $deleteImpactLoginServerName = null;

    #[Locked]
    public ?string $deleteImpactWarning = null;

    /** @var array<string,string> */
    public array $translations = [];

    public string $serverRates = '';

    public string $serverChronicle = '';

    public string $serverMode = '';

    public bool $connectionEnabled = false;

    public string $loginServerId = '';

    public string $driver = 'l2j_mobius_ct0_interlude';

    public bool $useLoginServerConnection = true;

    public string $databaseHost = '127.0.0.1';

    public string $databasePort = '3306';

    public string $databaseName = '';

    public string $databaseUsername = '';

    public string $databasePassword = '';

    public string $databaseCharset = 'utf8mb4';

    public string $serviceHost = '';

    public string $servicePort = '7777';

    /** @var array<string,mixed>|null */
    public ?array $connectionReport = null;

    public ?string $status = null;

    public bool $showChecks = false;

    /** @var array<int,array{state:string,message:string}> */
    public array $cardTestResults = [];

    public function mount(): void
    {
        $this->ensureAuthorized();
        $this->initializeTranslations();
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
        $server = GameServer::query()
            ->with(['translations', 'loginServer'])
            ->findOrFail($serverId);
        $languages = app(LanguageManager::class);

        $this->resetValidation();
        $this->editingId = $server->id;
        $this->translations = [];
        foreach ($languages->enabledCodes() as $locale) {
            $translation = $server->translations->firstWhere('locale', $locale);
            $this->translations[$locale] = $translation instanceof GameServerTranslation
                ? trim((string) $translation->name)
                : ($locale === $languages->default() ? trim((string) $server->name) : '');
        }

        $this->serverRates = trim((string) $server->rates);
        $this->serverChronicle = trim((string) $server->chronicle);
        $this->serverMode = trim((string) $server->mode);
        $this->connectionEnabled = $server->connectionConfigured();
        $this->loginServerId = $server->login_server_id !== null ? (string) $server->login_server_id : '';
        $this->driver = $server->driver ?? 'l2j_mobius_ct0_interlude';
        $this->useLoginServerConnection = $server->connectionConfigured()
            ? (bool) $server->use_login_server_connection
            : true;
        $this->databaseHost = trim((string) $server->database_host) !== '' ? trim((string) $server->database_host) : '127.0.0.1';
        $this->databasePort = (string) ($server->database_port ?? 3306);
        $this->databaseName = trim((string) $server->database_name);
        $this->databaseUsername = trim((string) $server->database_username);
        $this->databasePassword = '';
        $this->databaseCharset = trim((string) $server->database_charset) !== '' ? trim((string) $server->database_charset) : 'utf8mb4';
        $this->serviceHost = trim((string) $server->service_host);
        $this->servicePort = (string) ($server->service_port ?? 7777);
        $this->connectionReport = null;
        $this->status = null;
        $this->showChecks = false;
        $this->clearDeleteConfirmation();
        $this->drawerOpen = true;
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->connectionReport = null;
        $this->showChecks = false;
        $this->clearDeleteConfirmation();
        $this->resetValidation();
    }

    public function testStored(int $serverId): void
    {
        $this->ensureAuthorized();
        $server = GameServer::query()->with('loginServer')->findOrFail($serverId);
        $loginServer = $server->loginServer;

        if (! $loginServer instanceof LoginServer) {
            $this->cardTestResults[$server->id] = [
                'state' => 'failed',
                'message' => __('Select a LoginServer before testing the connection.'),
            ];

            return;
        }

        $report = app(ServerConnectionTester::class)->testGameServer($server);
        $this->auditConnectionTest($report, $server, $loginServer);
        $this->cardTestResults[$server->id] = $this->cardTestResult($report);
    }

    public function testConnection(): void
    {
        $this->ensureAuthorized();
        $this->connectionEnabled = true;
        $validated = $this->validate($this->connectionRules(), [], $this->connectionAttributes());
        $loginServer = LoginServer::query()->findOrFail((int) $validated['loginServerId']);
        $server = $this->editingId !== null
            ? GameServer::query()->findOrFail($this->editingId)
            : null;
        $values = $this->connectionValues($validated, $server);

        $report = app(ServerConnectionTester::class)->testGameValues($values, $loginServer);
        $this->auditConnectionTest($report, $server, $loginServer);

        $this->connectionReport = $report;
        $this->showChecks = false;
        $this->status = null;
        $this->drawerOpen = true;
    }

    public function save(): void
    {
        $this->ensureAuthorized();
        $general = $this->validate($this->generalRules(), [], $this->generalAttributes());
        $connection = $this->connectionEnabled
            ? $this->validate($this->connectionRules(), [], $this->connectionAttributes())
            : [];
        $settings = app(GameServerSettings::class);
        $audit = app(AuditLogger::class);
        $before = null;

        $server = DB::transaction(function () use ($general, $connection, $settings, &$before): GameServer {
            $values = $this->generalValues($general);

            if ($this->editingId === null) {
                $server = $settings->create($values);
            } else {
                $server = GameServer::query()->with('translations')->findOrFail($this->editingId);
                $before = $this->auditValues($server);
                $settings->update($server, $values);
                $server->refresh();
            }

            if ($this->connectionEnabled) {
                $this->saveConnection($server, $connection, $settings);
            } else {
                $settings->reassignLinkedAccountsBeforeDisconnect($server);
                $server->update([
                    'login_server_id' => null,
                    'driver' => null,
                    'use_login_server_connection' => true,
                    'database_host' => null,
                    'database_port' => null,
                    'database_name' => null,
                    'database_username' => null,
                    'database_password' => null,
                    'database_charset' => null,
                    'service_host' => null,
                    'service_port' => null,
                    'monitor_status' => 'unknown',
                    'monitor_failures' => 0,
                    'monitor_checked_at' => null,
                    'monitor_last_online_at' => null,
                    'online_players' => null,
                    'online_checked_at' => null,
                ]);
            }

            return $server->fresh(['translations', 'loginServer']) ?? $server;
        });

        if ($this->editingId === null) {
            $audit->success(
                category: 'admin',
                action: 'game_server.created',
                target: $server,
                details: ['values' => $this->auditValues($server)],
            );
            $this->editingId = $server->id;
            $this->status = __('Game server added.');
        } else {
            $audit->success(
                category: 'admin',
                action: 'game_server.updated',
                target: $server,
                details: [
                    'before' => $before,
                    'after' => $this->auditValues($server),
                ],
            );
            $this->status = __('Game server settings saved.');
        }

        unset($this->cardTestResults[$server->id]);
        $this->databasePassword = '';
        $this->connectionReport = null;
        $this->clearDeleteConfirmation();
    }

    public function confirmDelete(int $serverId): void
    {
        $this->ensureAuthorized();
        $server = GameServer::query()->with('loginServer')->findOrFail($serverId);
        $this->applyDeleteImpact(app(GameServerDeletionImpact::class)->analyze($server));
        $this->confirmingDeleteId = $serverId;
        $this->deleteImpactWarning = null;
    }

    public function cancelDelete(): void
    {
        $this->clearDeleteConfirmation();
    }

    public function deleteServer(): void
    {
        $this->ensureAuthorized();

        if ($this->confirmingDeleteId === null) {
            return;
        }

        $server = GameServer::query()->with(['translations', 'loginServer'])->findOrFail($this->confirmingDeleteId);
        $name = $server->name;
        $serverId = $server->id;
        $values = $this->auditValues($server);

        try {
            $impact = app(GameServerSettings::class)->delete($server, $this->deleteImpactFingerprint);
        } catch (GameServerDeletionConfirmationRequired $exception) {
            $this->applyDeleteImpact($exception->impact);
            $this->deleteImpactWarning = __('The deletion impact changed. Review the updated account count and confirm again.');

            return;
        }

        app(AuditLogger::class)->success(
            category: 'admin',
            action: 'game_server.deleted',
            target: $name,
            details: [
                'game_server_id' => $serverId,
                'values' => $values,
                'deletion_impact' => [
                    'login_server_id' => $impact['login_server_id'],
                    'replacement_game_server_id' => $impact['replacement_game_server_id'],
                    'accounts_becoming_unavailable' => $impact['accounts_becoming_unavailable'],
                    'unavailable_after_deletion' => $impact['unavailable_after_deletion'],
                ],
            ],
        );

        if ($this->editingId === $this->confirmingDeleteId) {
            $this->closeDrawer();
            $this->resetForm();
        }

        unset($this->cardTestResults[$serverId]);
        $this->status = __('Game server :name deleted.', ['name' => $name]);
        $this->clearDeleteConfirmation();
    }

    public function render(): View
    {
        return view('livewire.admin.game-server-manager', [
            'servers' => app(GameServerSettings::class)->all(),
            'loginServers' => LoginServer::query()->orderBy('name')->orderBy('id')->get(),
            'gameDrivers' => app(ServerDriverRegistry::class)->gameDrivers(),
            'languages' => app(LanguageManager::class)->enabled(),
            'defaultLocale' => app(LanguageManager::class)->default(),
        ]);
    }

    /** @return array<string,mixed> */
    private function generalRules(): array
    {
        $languages = app(LanguageManager::class);
        $rules = [
            'translations' => ['required', 'array'],
            'serverRates' => ['nullable', 'string', 'max:100'],
            'serverChronicle' => ['nullable', 'string', 'max:100'],
            'serverMode' => ['nullable', 'string', 'max:100'],
        ];

        foreach ($languages->enabledCodes() as $locale) {
            $rules['translations.'.$locale] = $locale === $languages->default()
                ? ['required', 'string', 'max:100']
                : ['nullable', 'string', 'max:100'];
        }

        return $rules;
    }

    /** @return array<string,mixed> */
    private function connectionRules(): array
    {
        $rules = [
            'loginServerId' => ['required', 'integer', 'exists:login_servers,id'],
            'driver' => ['required', Rule::in(app(ServerDriverRegistry::class)->gameDriverKeys())],
            'useLoginServerConnection' => ['required', 'boolean'],
            'databasePassword' => ['nullable', 'string', 'max:1024'],
            'serviceHost' => ['nullable', 'string', 'max:255'],
            'servicePort' => ['required', 'integer', 'between:1,65535'],
        ];

        if (! $this->useLoginServerConnection) {
            $rules += [
                'databaseHost' => ['required', 'string', 'max:255'],
                'databasePort' => ['required', 'integer', 'between:1,65535'],
                'databaseName' => ['required', 'string', 'max:64'],
                'databaseUsername' => ['required', 'string', 'max:128'],
                'databaseCharset' => ['required', Rule::in(['utf8mb4', 'utf8', 'latin1', 'cp1251'])],
            ];
        }

        return $rules;
    }

    /** @return array<string,string> */
    private function generalAttributes(): array
    {
        $attributes = [
            'serverRates' => __('Server rates validation attribute'),
            'serverChronicle' => __('Chronicle validation attribute'),
            'serverMode' => __('server mode'),
        ];

        foreach (app(LanguageManager::class)->enabledCodes() as $locale) {
            $attributes['translations.'.$locale] = __('Server name validation attribute');
        }

        return $attributes;
    }

    /** @return array<string,string> */
    private function connectionAttributes(): array
    {
        return [
            'loginServerId' => __('LoginServer'),
            'driver' => __('GameServer driver'),
            'useLoginServerConnection' => __('Use LoginServer database connection'),
            'databaseHost' => __('Database host'),
            'databasePort' => __('Database port'),
            'databaseName' => __('Database name'),
            'databaseUsername' => __('Database username'),
            'databasePassword' => __('Database password'),
            'databaseCharset' => __('Database charset'),
            'serviceHost' => __('Service host'),
            'servicePort' => __('Service port'),
        ];
    }

    /** @param array<string,mixed> $validated @return array{name:string,rates:string,chronicle:string,mode:string,translations:array<string,string>} */
    private function generalValues(array $validated): array
    {
        $translations = [];
        foreach ((array) $validated['translations'] as $locale => $name) {
            $translations[(string) $locale] = trim((string) $name);
        }
        $defaultLocale = app(LanguageManager::class)->default();

        return [
            'name' => $translations[$defaultLocale] ?? '',
            'rates' => trim((string) ($validated['serverRates'] ?? '')),
            'chronicle' => trim((string) ($validated['serverChronicle'] ?? '')),
            'mode' => trim((string) ($validated['serverMode'] ?? '')),
            'translations' => $translations,
        ];
    }

    /** @param array<string,mixed> $validated @return array<string,mixed> */
    private function connectionValues(array $validated, ?GameServer $server): array
    {
        $password = (string) ($validated['databasePassword'] ?? '');
        if ($password === '' && $server instanceof GameServer && ! $this->useLoginServerConnection) {
            $password = $server->databasePassword() ?? '';
        }

        return [
            'driver' => trim((string) $validated['driver']),
            'use_login_server_connection' => (bool) $validated['useLoginServerConnection'],
            'database_host' => trim((string) ($validated['databaseHost'] ?? '')),
            'database_port' => (int) ($validated['databasePort'] ?? 3306),
            'database_name' => trim((string) ($validated['databaseName'] ?? '')),
            'database_username' => trim((string) ($validated['databaseUsername'] ?? '')),
            'database_password' => $password,
            'database_charset' => trim((string) ($validated['databaseCharset'] ?? 'utf8mb4')),
            'service_host' => $this->nullableString($validated['serviceHost'] ?? null),
            'service_port' => (int) ($validated['servicePort'] ?? 7777),
        ];
    }

    /** @param array<string,mixed> $validated */
    private function saveConnection(GameServer $server, array $validated, GameServerSettings $settings): void
    {
        $loginServer = LoginServer::query()->findOrFail((int) $validated['loginServerId']);
        $useLoginConnection = (bool) $validated['useLoginServerConnection'];
        $password = (string) ($validated['databasePassword'] ?? '');
        $settings->reassignLinkedAccountsBeforeDisconnect($server, $loginServer->id);
        $values = [
            'login_server_id' => $loginServer->id,
            'driver' => trim((string) $validated['driver']),
            'use_login_server_connection' => $useLoginConnection,
            'database_host' => $useLoginConnection ? null : trim((string) $validated['databaseHost']),
            'database_port' => $useLoginConnection ? null : (int) $validated['databasePort'],
            'database_name' => $useLoginConnection ? null : trim((string) $validated['databaseName']),
            'database_username' => $useLoginConnection ? null : trim((string) $validated['databaseUsername']),
            'database_charset' => $useLoginConnection ? null : trim((string) $validated['databaseCharset']),
            'service_host' => $this->nullableString($validated['serviceHost'] ?? null),
            'service_port' => (int) $validated['servicePort'],
            'monitor_status' => 'unknown',
            'monitor_failures' => 0,
            'monitor_checked_at' => null,
            'monitor_last_online_at' => null,
            'online_players' => null,
            'online_checked_at' => null,
        ];

        if ($useLoginConnection) {
            $values['database_password'] = null;
        } elseif ($password !== '') {
            $values['database_password'] = $password;
        }

        $server->update($values);
        $settings->restoreOrphanedAccountLinks($server);
    }

    /** @return array<string,mixed> */
    private function auditValues(GameServer $server): array
    {
        return [
            'name' => $server->name,
            'rates' => $server->rates,
            'chronicle' => $server->chronicle,
            'mode' => $server->mode,
            'login_server_id' => $server->login_server_id,
            'driver' => $server->driver,
            'use_login_server_connection' => $server->use_login_server_connection,
            'database_host' => $server->database_host,
            'database_port' => $server->database_port,
            'database_name' => $server->database_name,
            'database_username' => $server->database_username,
            'database_charset' => $server->database_charset,
            'database_password_saved' => $server->hasDatabasePassword(),
            'service_host' => $server->service_host,
            'service_port' => $server->service_port,
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
    private function auditConnectionTest(array $report, ?GameServer $server, LoginServer $loginServer): void
    {
        $details = [
            'login_server_id' => $loginServer->id,
            'driver' => $report['driver'] ?? null,
            'connected' => $report['connected'] ?? false,
            'compatible' => $report['compatible'] ?? null,
        ];
        $audit = app(AuditLogger::class);
        $target = $server ?? ($this->translations[app(LanguageManager::class)->default()] ?? __('New game server'));

        if ($report['connected'] ?? false) {
            $audit->success(
                category: 'admin',
                action: 'game_server.connection_tested',
                target: $target,
                details: $details,
            );

            return;
        }

        $audit->failed(
            category: 'admin',
            action: 'game_server.connection_tested',
            target: $target,
            details: $details,
        );
    }

    private function initializeTranslations(): void
    {
        $this->translations = [];
        foreach (app(LanguageManager::class)->enabledCodes() as $locale) {
            $this->translations[$locale] = '';
        }
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->clearDeleteConfirmation();
        $this->initializeTranslations();
        $this->serverRates = '';
        $this->serverChronicle = '';
        $this->serverMode = '';
        $this->connectionEnabled = false;
        $this->loginServerId = '';
        $this->driver = 'l2j_mobius_ct0_interlude';
        $this->useLoginServerConnection = true;
        $this->databaseHost = '127.0.0.1';
        $this->databasePort = '3306';
        $this->databaseName = '';
        $this->databaseUsername = '';
        $this->databasePassword = '';
        $this->databaseCharset = 'utf8mb4';
        $this->serviceHost = '';
        $this->servicePort = '7777';
        $this->connectionReport = null;
        $this->status = null;
        $this->showChecks = false;
    }

    /**
     * @param array{
     *     game_server_id:int,
     *     login_server_id:int|null,
     *     login_server_name:string|null,
     *     replacement_game_server_id:int|null,
     *     login_server_account_count:int,
     *     accounts_becoming_unavailable:int,
     *     unavailable_after_deletion:int,
     *     requires_confirmation:bool,
     *     fingerprint:string
     * } $impact
     */
    private function applyDeleteImpact(array $impact): void
    {
        $this->deleteImpactFingerprint = $impact['fingerprint'];
        $this->deleteImpactRequiresConfirmation = $impact['requires_confirmation'];
        $this->deleteImpactAccountCount = $impact['accounts_becoming_unavailable'];
        $this->deleteImpactLoginServerName = $impact['login_server_name'];
    }

    private function clearDeleteConfirmation(): void
    {
        $this->confirmingDeleteId = null;
        $this->deleteImpactFingerprint = null;
        $this->deleteImpactRequiresConfirmation = false;
        $this->deleteImpactAccountCount = 0;
        $this->deleteImpactLoginServerName = null;
        $this->deleteImpactWarning = null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function ensureAuthorized(): void
    {
        abort_unless(auth('admin')->check(), 403);
    }
}
