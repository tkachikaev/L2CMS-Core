@if(is_array($report ?? null) && ($report['context'] ?? null) === $context)
    @php
        $connected = (bool) ($report['connected'] ?? false);
        $incompatible = $connected && ($report['compatible'] ?? null) === false;
    @endphp
    <div @class(['database-test-report', 'success' => $connected && ! $incompatible, 'warning' => $incompatible, 'failed' => ! $connected])>
        <div class="database-test-heading">
            <strong>
                {{ $connected ? __('Database connection established.') : __('Database connection failed.') }}
            </strong>
            @if(! empty($report['server_version']))
                <span>{{ __('Database server version: :version', ['version' => $report['server_version']]) }}</span>
            @endif
        </div>

        @if(! $connected)
            <p>{{ __('Check the host, port, database name, credentials and firewall rules.') }}</p>
        @elseif(! ($report['driver_ready'] ?? true))
            <p>{{ __('The database is reachable. This driver is a placeholder, so its tables are not checked yet.') }}</p>
        @else
            <p>
                {{ ($report['compatible'] ?? false)
                    ? __('The database schema is compatible with the selected driver.')
                    : __('The database is reachable, but required driver tables or columns are missing.') }}
            </p>

            @if(($report['checks'] ?? []) !== [])
                <div class="database-test-checks">
                    @foreach($report['checks'] as $check)
                        @php
                            $passed = ($check['table_exists'] ?? false) && ($check['missing_columns'] ?? []) === [];
                        @endphp
                        <div class="database-test-check">
                            <span @class(['status-badge', 'status-badge-success' => $passed, 'status-badge-danger' => ! $passed && ($check['required'] ?? false), 'status-badge-muted' => ! $passed && ! ($check['required'] ?? false)])>
                                {{ $passed ? __('Found') : __('Missing') }}
                            </span>
                            <strong>{{ $check['table'] }}</strong>
                            <small>{{ ($check['required'] ?? false) ? __('Required table') : __('Optional table') }}</small>
                            @if(($check['missing_columns'] ?? []) !== [])
                                <code>{{ __('Missing columns: :columns', ['columns' => implode(', ', $check['missing_columns'])]) }}</code>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endif
