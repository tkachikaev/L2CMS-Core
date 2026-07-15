@extends('account.layouts.app')
@section('title', __('Create game account'))
@section('content')
<div class="account-page-heading">
    <a href="{{ public_route('account') }}">← {{ __('My accounts') }}</a>
    <span class="account-eyebrow">{{ __('Game accounts') }}</span>
    <h1>{{ __('Create game account') }}</h1>
    <p>{{ __('Choose a game world and set the login credentials. The form contains only the required fields.') }}</p>
</div>

<form class="account-form-card" method="POST" action="{{ public_route('game-accounts.store') }}">
    @csrf
    <label>
        <span>{{ __('Game server') }}</span>
        <select name="game_server_id" required>
            <option value="">{{ __('Select a game server') }}</option>
            @foreach ($gameServers as $server)
                <option value="{{ $server->id }}" @selected((int) old('game_server_id') === $server->id)>
                    {{ $server->nameFor() }}@if($server->rates) — {{ $server->rates }}@endif
                </option>
            @endforeach
        </select>
        <small>{{ __('The selected world determines the LoginServer where the account will be created.') }}</small>
    </label>

    <label>
        <span>{{ __('Game login') }}</span>
        <input type="text" name="game_login" value="{{ old('game_login') }}" autocomplete="username" required maxlength="{{ $settings['login_max'] }}">
        <small>{{ __('Latin letters and digits, from :min to :max characters.', ['min' => $settings['login_min'], 'max' => $settings['login_max']]) }}</small>
    </label>

    <div class="account-form-grid">
        <label><span>{{ __('Game password') }}</span><input type="password" name="game_password" autocomplete="new-password" required maxlength="{{ $settings['password_max'] }}"></label>
        <label><span>{{ __('Repeat game password') }}</span><input type="password" name="game_password_confirmation" autocomplete="new-password" required maxlength="{{ $settings['password_max'] }}"></label>
    </div>

    <div class="account-form-note">
        <strong>{{ __('Password policy') }}</strong>
        <span>{{ __('From :min to :max characters.', ['min' => $settings['password_min'], 'max' => $settings['password_max']]) }}</span>
        @if($settings['password_lower'])<span>{{ __('Lowercase letter required.') }}</span>@endif
        @if($settings['password_upper'])<span>{{ __('Uppercase letter required.') }}</span>@endif
        @if($settings['password_digit'])<span>{{ __('Digit required.') }}</span>@endif
    </div>

    <div class="account-form-actions">
        <a class="account-button secondary" href="{{ public_route('account') }}">{{ __('Cancel') }}</a>
        <button class="account-button primary" type="submit">{{ __('Create account') }}</button>
    </div>
</form>
@endsection
