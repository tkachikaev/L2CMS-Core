@extends('admin.layouts.panel')

@section('title', __('Settings'))
@section('description', __('LoginServer database connections used by game worlds and game accounts.'))

@section('content')
@include('admin.settings._tabs')

@php
    $createContext = old('form_context') === 'login-create';
@endphp

<div class="settings-server-toolbar">
    <div>
        <span>{{ __('LoginServer count') }}</span>
        <strong>{{ $servers->count() }}</strong>
    </div>

    <details class="settings-add-server" @if($createContext) open @endif>
        <summary class="button button-primary">+ {{ __('Add LoginServer') }}</summary>
        <form method="POST" action="{{ route('admin.settings.login-server.store') }}">
            @csrf
            <input type="hidden" name="form_context" value="login-create">

            <div class="settings-card-heading">
                <h2>{{ __('New LoginServer') }}</h2>
                <p>{{ __('Configure the database containing accounts, account_data and accounts_ipauth.') }}</p>
            </div>

            <div class="server-connection-form-grid">
                <div class="form-group">
                    <label for="new_login_name">{{ __('Name') }}</label>
                    <input id="new_login_name" name="name" type="text" maxlength="100" value="{{ $createContext ? old('name', '') : '' }}" placeholder="{{ __('Primary LoginServer') }}" required>
                </div>
                <div class="form-group">
                    <label for="new_login_driver">{{ __('LoginServer driver') }}</label>
                    <select id="new_login_driver" name="driver" required>
                        @foreach($drivers as $key => $driver)
                            <option value="{{ $key }}" @selected(($createContext ? old('driver', 'l2j_mobius') : 'l2j_mobius') === $key)>
                                {{ $driver['label'] }}@if(!$driver['ready']) — {{ __('placeholder') }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            @include('admin.settings._database-fields', [
                'fieldPrefix' => 'new_login',
                'values' => [
                    'host' => $createContext ? old('database_host', '127.0.0.1') : '127.0.0.1',
                    'port' => $createContext ? old('database_port', 3306) : 3306,
                    'name' => $createContext ? old('database_name', '') : '',
                    'username' => $createContext ? old('database_username', '') : '',
                    'charset' => $createContext ? old('database_charset', 'utf8mb4') : 'utf8mb4',
                ],
                'passwordSaved' => false,
            ])

            @include('admin.settings._database-connection-report', ['context' => 'login-create'])

            <div class="settings-inline-actions server-connection-actions">
                <button class="button button-secondary" type="submit" name="connection_action" value="test">{{ __('Test connection') }}</button>
                <button class="button button-primary" type="submit" name="connection_action" value="save">{{ __('Save LoginServer') }}</button>
            </div>
        </form>
    </details>
</div>

@if($servers->isEmpty())
    <div class="empty-state">
        <div class="empty-state-mark" aria-hidden="true">L</div>
        <h2>{{ __('No LoginServers configured') }}</h2>
        <p>{{ __('Add a LoginServer before connecting a GameServer database.') }}</p>
    </div>
@else
    <div class="settings-server-list">
        @foreach($servers as $server)
            @php
                $context = 'login-'.$server->id;
                $hasOldInput = old('form_context') === $context;
                $selectedDriver = $hasOldInput ? old('driver', $server->driver) : $server->driver;
            @endphp
            <article class="form-card settings-server-card">
                <div class="settings-server-card-header">
                    <div>
                        <span class="settings-server-number">{{ __('LoginServer :number', ['number' => $loop->iteration]) }}</span>
                        <h2>{{ $server->name }}</h2>
                        <small>{{ trans_choice(':count game server|:count game servers', $server->game_servers_count, ['count' => $server->game_servers_count]) }} · {{ trans_choice(':count player account|:count player accounts', $server->user_game_accounts_count, ['count' => $server->user_game_accounts_count]) }}</small>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.login-server.destroy', $server) }}">
                        @csrf
                        @method('DELETE')
                        <button class="button button-danger" type="submit" @disabled($server->game_servers_count > 0 || $server->user_game_accounts_count > 0)>{{ __('Delete') }}</button>
                    </form>
                </div>

                <form class="server-connection-form" method="POST" action="{{ route('admin.settings.login-server.update', $server) }}">
                    @csrf
                    <input type="hidden" name="form_context" value="{{ $context }}">

                    <div class="server-connection-form-grid">
                        <div class="form-group">
                            <label for="login_{{ $server->id }}_name">{{ __('Name') }}</label>
                            <input id="login_{{ $server->id }}_name" name="name" type="text" maxlength="100" value="{{ $hasOldInput ? old('name', $server->name) : $server->name }}" required>
                        </div>
                        <div class="form-group">
                            <label for="login_{{ $server->id }}_driver">{{ __('LoginServer driver') }}</label>
                            <select id="login_{{ $server->id }}_driver" name="driver" required>
                                @foreach($drivers as $key => $driver)
                                    <option value="{{ $key }}" @selected($selectedDriver === $key)>
                                        {{ $driver['label'] }}@if(!$driver['ready']) — {{ __('placeholder') }}@endif
                                    </option>
                                @endforeach
                            </select>
                            <small>{{ $drivers[$selectedDriver]['description'] ?? '' }}</small>
                        </div>
                    </div>

                    @include('admin.settings._database-fields', [
                        'fieldPrefix' => 'login_'.$server->id,
                        'values' => [
                            'host' => $hasOldInput ? old('database_host', $server->database_host) : $server->database_host,
                            'port' => $hasOldInput ? old('database_port', $server->database_port) : $server->database_port,
                            'name' => $hasOldInput ? old('database_name', $server->database_name) : $server->database_name,
                            'username' => $hasOldInput ? old('database_username', $server->database_username) : $server->database_username,
                            'charset' => $hasOldInput ? old('database_charset', $server->database_charset) : $server->database_charset,
                        ],
                        'passwordSaved' => $server->hasDatabasePassword(),
                    ])

                    @include('admin.settings._database-connection-report', ['context' => $context])

                    <div class="settings-inline-actions server-connection-actions">
                        <button class="button button-secondary" type="submit" name="connection_action" value="test">{{ __('Test connection') }}</button>
                        <button class="button button-primary" type="submit" name="connection_action" value="save">{{ __('Save changes') }}</button>
                    </div>
                </form>
            </article>
        @endforeach
    </div>
@endif
@endsection
