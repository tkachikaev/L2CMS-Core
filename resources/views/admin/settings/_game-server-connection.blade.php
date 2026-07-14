@php
    $context = 'game-'.$server['id'];
    $hasConnectionOldInput = old('form_context') === $context;
    $selectedLoginServer = $hasConnectionOldInput ? old('login_server_id', $server['login_server_id']) : $server['login_server_id'];
    $selectedDriver = $hasConnectionOldInput ? old('driver', $server['driver'] ?? 'l2j_mobius_ct0_interlude') : ($server['driver'] ?? 'l2j_mobius_ct0_interlude');
    $useLoginConnection = $hasConnectionOldInput
        ? (string) old('use_login_server_connection', '1') === '1'
        : ($server['connection_configured'] ? $server['use_login_server_connection'] : true);
@endphp

<details class="settings-connection-placeholder server-connection-section" @if($hasConnectionOldInput || ($report['context'] ?? null) === $context) open @endif>
    <summary>
        <span>
            <strong>{{ __('GameServer database connection') }}</strong>
            <small>
                {{ $server['connection_configured']
                    ? __('Configured: :driver', ['driver' => $gameDrivers[$server['driver']]['label'] ?? $server['driver']])
                    : __('Connection is not configured yet.') }}
            </small>
        </span>
        <span @class(['status-badge', 'status-badge-success' => $server['connection_configured'], 'status-badge-muted' => ! $server['connection_configured']])>
            {{ $server['connection_configured'] ? __('Configured') : __('Not configured') }}
        </span>
    </summary>

    @if($loginServers->isEmpty())
        <div class="settings-disabled-notice">
            {{ __('Create a LoginServer first, then return here to configure the GameServer database.') }}
            <a href="{{ route('admin.settings.login-server') }}">{{ __('Open LoginServers') }}</a>
        </div>
    @else
        <form class="server-connection-form" method="POST" action="{{ route('admin.settings.game-server.connection', $server['id']) }}" data-game-server-connection-form>
            @csrf
            <input type="hidden" name="form_context" value="{{ $context }}">

            <div class="server-connection-form-grid">
                <div class="form-group">
                    <label for="{{ $fieldPrefix }}_login_server">{{ __('LoginServer') }}</label>
                    <select id="{{ $fieldPrefix }}_login_server" name="login_server_id" required>
                        <option value="">— {{ __('Select LoginServer') }} —</option>
                        @foreach($loginServers as $loginServer)
                            <option value="{{ $loginServer->id }}" @selected((string) $selectedLoginServer === (string) $loginServer->id)>{{ $loginServer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="{{ $fieldPrefix }}_driver">{{ __('GameServer driver') }}</label>
                    <select id="{{ $fieldPrefix }}_driver" name="driver" required>
                        @foreach($gameDrivers as $key => $driver)
                            <option value="{{ $key }}" @selected($selectedDriver === $key)>
                                {{ $driver['label'] }}@if(!$driver['ready']) — {{ __('placeholder') }}@endif
                            </option>
                        @endforeach
                    </select>
                    <small>{{ $gameDrivers[$selectedDriver]['description'] ?? '' }}</small>
                </div>
            </div>

            <label class="server-connection-toggle">
                <input type="hidden" name="use_login_server_connection" value="0">
                <input type="checkbox" name="use_login_server_connection" value="1" @checked($useLoginConnection) data-use-login-connection>
                <span>
                    <strong>{{ __('Use the selected LoginServer database parameters') }}</strong>
                    <small>{{ __('Host, port, database, user, password and charset will be taken from the LoginServer.') }}</small>
                </span>
            </label>

            @include('admin.settings._database-fields', [
                'fieldPrefix' => $fieldPrefix.'_game',
                'values' => [
                    'host' => $hasConnectionOldInput ? old('database_host', $server['database_host']) : $server['database_host'],
                    'port' => $hasConnectionOldInput ? old('database_port', $server['database_port'] ?? 3306) : ($server['database_port'] ?? 3306),
                    'name' => $hasConnectionOldInput ? old('database_name', $server['database_name']) : $server['database_name'],
                    'username' => $hasConnectionOldInput ? old('database_username', $server['database_username']) : $server['database_username'],
                    'charset' => $hasConnectionOldInput ? old('database_charset', $server['database_charset'] ?: 'utf8mb4') : ($server['database_charset'] ?: 'utf8mb4'),
                ],
                'passwordSaved' => $server['database_password_saved'],
                'disabled' => $useLoginConnection,
            ])

            @include('admin.settings._database-connection-report', ['context' => $context])

            <div class="settings-inline-actions server-connection-actions">
                <button class="button button-secondary" type="submit" name="connection_action" value="test">{{ __('Test connection') }}</button>
                <button class="button button-primary" type="submit" name="connection_action" value="save">{{ __('Save connection') }}</button>
            </div>
        </form>
    @endif
</details>
