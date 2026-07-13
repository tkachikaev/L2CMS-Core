@extends('theme::layouts.app')

@section('title', __('New password').' — '.site_name())

@section('content')
<section class="auth-page">
    <div class="panel auth-card">
        <p class="eyebrow">{{ __('ACCESS RECOVERY') }}</p>
        <h1>{{ __('New password') }}</h1>

        <form method="POST" action="{{ public_route('password.store') }}">
            @csrf
            <input name="token" type="hidden" value="{{ $token }}">

            <label for="email">Email
                <input id="email" name="email" type="email" maxlength="255" required autocomplete="email" value="{{ old('email', $email) }}">
            </label>

            <label for="password">{{ __('New password') }}
                <input id="password" name="password" type="password" minlength="8" required autofocus autocomplete="new-password">
                <small>{{ __('At least 8 characters, including at least one letter and one digit.') }}</small>
            </label>

            <label for="password_confirmation">{{ __('Confirm password') }}
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
            </label>

            <button class="button button-gold" type="submit">{{ __('Save new password') }}</button>
        </form>
    </div>
</section>
@endsection
