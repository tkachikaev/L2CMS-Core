@extends('theme::layouts.app')

@section('title', 'Вход — '.site_name())

@section('content')
<section class="auth-page">
    <div class="panel auth-card">
        <p class="eyebrow">ЛИЧНЫЙ КАБИНЕТ</p>
        <h1>Вход</h1>
        <p class="muted">Используйте логин или email учётной записи сайта.</p>

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label for="login">Логин или email
                <input id="login" name="login" type="text" maxlength="255" required autofocus autocomplete="username" value="{{ old('login') }}">
            </label>

            <label for="password">Пароль
                <input id="password" name="password" type="password" required autocomplete="current-password">
            </label>

            <label class="auth-checkbox" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                <span>Запомнить меня</span>
            </label>

            <button class="button button-gold" type="submit">Войти</button>
        </form>

        <div class="auth-links">
            <a href="{{ route('password.request') }}">Забыли пароль?</a>
            @if (registration_available())
                <a href="{{ route('register') }}">Создать учётную запись</a>
            @endif
        </div>
    </div>
</section>
@endsection
