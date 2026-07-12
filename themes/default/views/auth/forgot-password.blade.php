@extends('theme::layouts.app')

@section('title', 'Восстановление пароля — '.site_name())

@section('content')
<section class="auth-page">
    <div class="panel auth-card">
        <p class="eyebrow">ВОССТАНОВЛЕНИЕ ДОСТУПА</p>
        <h1>Забыли пароль?</h1>
        <p class="muted">Укажите email учётной записи. Мы отправим ссылку для установки нового пароля.</p>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label for="email">Email
                <input id="email" name="email" type="email" maxlength="255" required autofocus autocomplete="email" value="{{ old('email') }}">
            </label>
            <button class="button button-gold" type="submit">Отправить ссылку</button>
        </form>

        <div class="auth-links auth-links-center">
            <a href="{{ route('login') }}">Вернуться ко входу</a>
        </div>
    </div>
</section>
@endsection
