@extends('admin.layouts.panel')

@section('title', __('System information'))
@section('description', __('Versions, environment and KaevCMS component status.'))

@section('content')
<section class="system-overview">
    <div>
        <span class="system-eyebrow">KaevCMS</span>
        <strong>{{ __('Version :version', ['version' => $system['cms']['version']]) }}</strong>
        <p>{!! __('The version is read from the <code>VERSION</code> file in the project root.') !!}</p>
    </div>
    <div class="system-overview-actions">
        <a wire:navigate class="button button-secondary" href="{{ route('admin.settings.system') }}">{{ __('Refresh information') }}</a>
        <button
            class="button button-primary"
            type="button"
            data-copy-system-report
            data-copy-success="{{ __('Report copied.') }}"
            data-copy-error="{{ __('Could not copy the report.') }}"
        >{{ __('Copy report') }}</button>
    </div>
</section>

<section class="form-card system-monitor-settings-card">
    <form method="POST" action="{{ route('admin.settings.system.monitoring.update') }}">
        @csrf
        @method('PUT')

        <div class="system-monitor-settings-heading">
            <div>
                <h2>{{ __('Server monitoring') }}</h2>
                <p>{{ __('CMS refreshes stale server status when the public home page or administrator dashboard is opened.') }}</p>
            </div>
        </div>

        <div class="system-monitor-settings-controls">
            <div class="form-group">
                <div class="field-label-with-help">
                    <label for="refresh_interval_seconds">{{ __('Server status refresh interval') }}</label>
                    <span class="field-help-tooltip" tabindex="0" aria-label="{{ __('About the server status refresh interval') }}">
                        <span class="field-help-tooltip-icon" aria-hidden="true">?</span>
                        <span class="field-help-tooltip-content" role="tooltip">{{ __('How often CMS may repeat LoginServer and GameServer availability checks and update the online player count. Between checks, the site uses the saved result.') }}</span>
                    </span>
                </div>
                <select id="refresh_interval_seconds" name="refresh_interval_seconds" required>
                    @foreach($monitorRefreshOptions as $seconds)
                        <option
                            value="{{ $seconds }}"
                            @selected((int) old('refresh_interval_seconds', $monitorSettings['refresh_interval_seconds']) === $seconds)
                        >
                            {{ match ($seconds) {
                                30 => __('30 seconds'),
                                60 => __('1 minute'),
                                120 => __('2 minutes'),
                                300 => __('5 minutes'),
                                default => __(':count seconds', ['count' => $seconds]),
                            } }}
                        </option>
                    @endforeach
                </select>
                <small>{{ __('Minimum: 30 seconds. Default: 1 minute.') }}</small>
                @error('refresh_interval_seconds')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <button class="button button-primary" type="submit">{{ __('Save monitoring settings') }}</button>
        </div>
    </form>
</section>

<div class="system-information-grid">
    <section class="form-card system-card">
        <h2>{{ __('Software') }}</h2>
        <dl class="system-definition-list">
            <div><dt>PHP</dt><dd>{{ $system['software']['php'] }}</dd></div>
            <div><dt>Laravel</dt><dd>{{ $system['software']['laravel'] }}</dd></div>
            <div><dt>Composer</dt><dd>{{ $system['software']['composer'] }}</dd></div>
            <div><dt>{{ __('Operating system') }}</dt><dd>{{ $system['software']['os'] }}</dd></div>
            <div><dt>{{ __('PHP architecture') }}</dt><dd>{{ $system['software']['architecture'] }}</dd></div>
            <div><dt>PHP SAPI</dt><dd><code>{{ $system['software']['sapi'] }}</code></dd></div>
            <div>
                <dt>{{ __('Password hash') }}</dt>
                <dd>
                    <code>{{ $system['security']['label'] }}</code>
                    @if($system['security']['driver'] === 'bcrypt' && ! $system['security']['argon2id_supported'])
                        <small class="system-definition-note">{{ __('Argon2id is not supported by the system.') }}</small>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    <section class="form-card system-card">
        <h2>{{ __('Laravel environment') }}</h2>
        <dl class="system-definition-list">
            <div><dt>{{ __('Environment') }}</dt><dd><code>{{ $system['environment']['name'] }}</code></dd></div>
            <div><dt>{{ __('Debug mode') }}</dt><dd><span class="status-badge {{ $system['environment']['debug'] ? 'status-badge-warning' : 'status-badge-success' }}">{{ $system['environment']['debug'] ? __('Enabled') : __('Disabled') }}</span></dd></div>
            <div><dt>{{ __('PHP time zone') }}</dt><dd>{{ $system['environment']['php_timezone'] }}</dd></div>
            <div><dt>{{ __('CMS time zone') }}</dt><dd>{{ $system['environment']['cms_timezone'] }}</dd></div>
            <div><dt>{{ __('Cache') }}</dt><dd><code>{{ $system['environment']['cache'] }}</code></dd></div>
            <div><dt>{{ __('Sessions') }}</dt><dd><code>{{ $system['environment']['session'] }}</code></dd></div>
            <div><dt>{{ __('Queues') }}</dt><dd><code>{{ $system['environment']['queue'] }}</code></dd></div>
            <div><dt>{{ __('Mail') }}</dt><dd><code>{{ $system['environment']['mail'] }}</code></dd></div>
            <div><dt>{{ __('Laravel logs') }}</dt><dd><code>{{ $system['environment']['logging'] }}</code></dd></div>
        </dl>
    </section>

    <section class="form-card system-card">
        <h2>{{ __('CMS database') }}</h2>
        <dl class="system-definition-list">
            <div><dt>{{ __('Connection') }}</dt><dd><code>{{ $system['database']['connection'] }}</code></dd></div>
            <div><dt>{{ __('Driver') }}</dt><dd>{{ $system['database']['driver_label'] }}</dd></div>
            <div><dt>{{ __('Server version') }}</dt><dd>{{ $system['database']['version'] ?: __('Could not determine') }}</dd></div>
            <div><dt>{{ __('Status') }}</dt><dd><span class="status-badge {{ $system['database']['connected'] ? 'status-badge-success' : 'status-badge-danger' }}">{{ $system['database']['connected'] ? __('Connected') : __('Connection error') }}</span></dd></div>
            @if($system['database']['path'])
                <div><dt>{{ __('File') }}</dt><dd><code>{{ $system['database']['path'] }}</code></dd></div>
            @endif
            @if($system['database']['size'])
                <div><dt>{{ __('Size') }}</dt><dd>{{ $system['database']['size'] }}</dd></div>
            @endif
        </dl>
    </section>
</div>

<section class="form-card system-components-card">
    <div class="system-section-heading">
        <div>
            <h2>{{ __('Component status') }}</h2>
            <p>{{ __('Checks run again whenever this page is opened.') }}</p>
        </div>
    </div>
    <div class="system-component-list">
        @foreach($system['components'] as $component)
            <article class="system-component-row">
                <span @class([
                    'system-status-dot',
                    'success' => $component['state'] === 'success',
                    'warning' => $component['state'] === 'warning',
                    'danger' => $component['state'] === 'danger',
                    'neutral' => $component['state'] === 'neutral',
                ]) aria-hidden="true"></span>
                <div>
                    <strong>{{ $component['label'] }}</strong>
                    <small>{{ $component['details'] }}</small>
                </div>
                <span @class([
                    'status-badge',
                    'status-badge-success' => $component['state'] === 'success',
                    'status-badge-warning' => $component['state'] === 'warning',
                    'status-badge-danger' => $component['state'] === 'danger',
                    'status-badge-muted' => $component['state'] === 'neutral',
                ])>{{ $component['status'] }}</span>
            </article>
        @endforeach
    </div>
</section>

<section class="form-card system-extensions-card">
    <div class="system-section-heading">
        <div>
            <h2>{{ __('PHP extensions') }}</h2>
            <p>{{ __('Required extensions are also checked by setup and diagnostic scripts.') }}</p>
        </div>
    </div>
    <div class="system-extension-grid">
        @foreach($system['extensions'] as $extension)
            <div @class(['system-extension', 'missing' => ! $extension['loaded']])>
                <div>
                    <code>{{ $extension['name'] }}</code>
                    <small>{{ $extension['required'] ? __('Required') : __('Optional') }}</small>
                </div>
                <span class="status-badge {{ $extension['loaded'] ? 'status-badge-success' : ($extension['required'] ? 'status-badge-danger' : 'status-badge-muted') }}">{{ $extension['loaded'] ? __('Installed') : __('Not installed') }}</span>
            </div>
        @endforeach
    </div>
</section>

<section class="form-card system-report-card">
    <div class="system-section-heading">
        <div>
            <h2>{{ __('Safe support report') }}</h2>
            <p>{{ __('The report excludes APP_KEY, passwords, tokens, cookies, database usernames and absolute paths.') }}</p>
        </div>
        <span class="system-copy-state" data-system-copy-state aria-live="polite"></span>
    </div>
    <pre data-system-report-preview>{{ $system['report'] }}</pre>
    <textarea class="system-report-source" data-system-report readonly aria-hidden="true" tabindex="-1">{{ $system['report'] }}</textarea>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/system.js') }}?v={{ cms_version() }}" defer data-navigate-once></script>
@endpush
