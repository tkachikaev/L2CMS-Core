@extends('admin.layouts.panel')

@section('title', __('Settings'))
@section('description', __('Game worlds displayed on the public website.'))

@section('content')
@include('admin.settings._tabs')

@php
    $createHasOldInput = old('form_context') === 'create';
    $createValues = [
        'rates' => $createHasOldInput ? old('server_rates', '') : '',
        'chronicle' => $createHasOldInput ? old('server_chronicle', '') : '',
        'mode' => $createHasOldInput ? old('server_mode', '') : '',
    ];
@endphp

<div class="settings-server-toolbar">
    <div>
        <span>{{ __('Game servers') }}</span>
        <strong>{{ count($servers) }}</strong>
    </div>

    <details class="settings-add-server" @if($createHasOldInput) open @endif>
        <summary class="button button-primary">+ {{ __('Add game server') }}</summary>
        <form method="POST" action="{{ route('admin.settings.game-server.store') }}">
            @csrf
            <input type="hidden" name="form_context" value="create">

            <div class="settings-card-heading">
                <div>
                    <h2>{{ __('New game server') }}</h2>
                    <p>{{ __('After saving, the server will appear in the default theme.') }}</p>
                </div>
            </div>

            @include('admin.settings._game-server-fields', [
                'fieldPrefix' => 'new_server',
                'values' => $createValues,
                'translationValues' => [],
                'hasOldInput' => $createHasOldInput,
            ])

            <div class="settings-inline-actions">
                <button class="button button-primary" type="submit">{{ __('Add server') }}</button>
            </div>
        </form>
    </details>
</div>

@if ($servers === [])
    <div class="empty-state">
        <div class="empty-state-mark" aria-hidden="true">S</div>
        <h2>{{ __('No game servers added') }}</h2>
        <p>{{ __('Add the first game world. The server block is hidden in the default theme while the list is empty.') }}</p>
    </div>
@else
    <div class="settings-server-list">
        @foreach ($servers as $server)
            @php
                $context = 'server-'.$server['id'];
                $hasOldInput = old('form_context') === $context;
                $values = [
                    'rates' => $hasOldInput ? old('server_rates', '') : $server['rates'],
                    'chronicle' => $hasOldInput ? old('server_chronicle', '') : $server['chronicle'],
                    'mode' => $hasOldInput ? old('server_mode', '') : $server['mode'],
                ];
                $fieldPrefix = 'server_'.$server['id'];
            @endphp

            <article class="form-card settings-server-card">
                <div class="settings-server-card-header">
                    <div>
                        <span class="settings-server-number">{{ __('Server :number', ['number' => $loop->iteration]) }}</span>
                        <h2>{{ $server['name'] }}</h2>
                    </div>
                    <button class="button button-danger" type="button" data-game-server-delete-open data-game-server-delete-name="{{ $server['name'] }}" data-game-server-delete-url="{{ route('admin.settings.game-server.destroy', $server['id']) }}">{{ __('Delete') }}</button>
                </div>

                <form class="settings-server-edit-form" method="POST" action="{{ route('admin.settings.game-server.update', $server['id']) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="form_context" value="{{ $context }}">

                    @include('admin.settings._game-server-fields', [
                        'fieldPrefix' => $fieldPrefix,
                        'values' => $values,
                        'translationValues' => $server['translations'],
                        'hasOldInput' => $hasOldInput,
                    ])

                    <div class="settings-inline-actions">
                        <button class="button button-primary" type="submit">{{ __('Save server') }}</button>
                    </div>
                </form>

                @include('admin.settings._game-server-connection', ['fieldPrefix' => $fieldPrefix])
            </article>
        @endforeach
    </div>

    <dialog class="confirm-dialog" data-game-server-delete-dialog aria-labelledby="delete-game-server-title">
        <div class="confirm-dialog-card">
            <div class="confirm-dialog-copy">
                <span class="confirm-dialog-mark" aria-hidden="true">!</span>
                <div>
                    <h2 id="delete-game-server-title">{{ __('Delete game server?') }}</h2>
                    <p>{!! __('The server “<strong data-game-server-delete-name></strong>” will be removed from settings and the default theme. This action cannot be undone.') !!}</p>
                </div>
            </div>
            <div class="confirm-dialog-actions">
                <button class="button button-secondary" type="button" data-game-server-delete-cancel>{{ __('Cancel') }}</button>
                <form method="POST" action="" data-game-server-delete-form>
                    @csrf
                    @method('DELETE')
                    <button class="button button-danger" type="submit">{{ __('Yes, delete') }}</button>
                </form>
            </div>
        </div>
    </dialog>
@endif
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/game-server-actions.js') }}" defer></script>
<script src="{{ asset('assets/admin/js/localization.js') }}" defer></script>
@endpush
