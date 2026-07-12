@extends('theme::layouts.app')

@section('title', 'Новый пароль — '.site_name())

@section('content')
<section class="auth-page">
    <div class="panel auth-card">
        <p class="eyebrow">ВОССТАНОВЛЕНИЕ ДОСТУПА</p>
        <h1>Новый пароль</h1>

        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input name="token" type="hidden" value="{{ $token }}">

            <label for="email">Email
                <input id="email" name="email" type="email" maxlength="255" required autocomplete="email" value="{{ old('email', $email) }}">
            </label>

            <label for="password">Новый пароль
                <input id="password" name="password" type="password" minlength="8" required autofocus autocomplete="new-password">
                <small>Не менее 8 символов, минимум одна буква и одна цифра.</small>
            </label>

            <label for="password_confirmation">Повторите пароль
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
            </label>

            <button class="button button-gold" type="submit">Сохранить новый пароль</button>
        </form>
    </div>
</section>
@endsection
