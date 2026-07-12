@extends('theme::layouts.app')

@section('title', 'Регистрация — '.site_name())

@section('content')
<section class="auth-page">
    <div class="panel auth-card">
        <p class="eyebrow">НОВАЯ УЧЁТНАЯ ЗАПИСЬ</p>
        <h1>Регистрация</h1>
        <p class="muted">Создаётся пользователь сайта. Игровой аккаунт Lineage II регистрируется отдельно.</p>

        <form method="POST" action="{{ route('register.store') }}">
            @csrf

            <label for="name">Логин
                <input id="name" name="name" type="text" minlength="3" maxlength="32" required autofocus autocomplete="username" value="{{ old('name') }}" pattern="[A-Za-z0-9_-]+">
                <small>Латинские буквы, цифры, дефис и подчёркивание.</small>
            </label>

            <label for="email">Email
                <input id="email" name="email" type="email" maxlength="255" required autocomplete="email" value="{{ old('email') }}">
            </label>

            <label for="password">Пароль
                <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
                <small>Не менее 8 символов, минимум одна буква и одна цифра.</small>
            </label>

            <label for="password_confirmation">Повторите пароль
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
            </label>

            @if ($emailVerificationRequired)
                <p class="auth-info">После регистрации на указанный email придёт ссылка подтверждения.</p>
            @endif

            <button class="button button-gold" type="submit">Создать учётную запись</button>
        </form>

        <div class="auth-links auth-links-center">
            <a href="{{ route('login') }}">Уже зарегистрированы? Войти</a>
        </div>
    </div>
</section>
@endsection
