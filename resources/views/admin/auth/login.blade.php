@extends('admin.layouts.app')

@section('title', 'Вход администратора')

@section('body')
<main class="login-shell">
    <section class="login-panel" aria-labelledby="login-title">
        <a class="brand" href="{{ route('home') }}" aria-label="Вернуться на сайт">
            <span class="brand-mark">L2</span>
            <span>
                <strong>{{ config('app.name') }}</strong>
                <small>CONTROL PANEL</small>
            </span>
        </a>

        <div class="login-copy">
            <p class="eyebrow">ЗАЩИЩЁННАЯ ЗОНА</p>
            <h1 id="login-title">Вход администратора</h1>
            <p>Используйте отдельную учётную запись CMS. Игровой логин здесь не подходит.</p>
        </div>

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}" class="login-form">
            @csrf

            <label for="email">Электронная почта</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="username"
                inputmode="email"
                maxlength="255"
                required
                autofocus
                @class(['field-error' => $errors->has('email')])
            >
            @error('email')<p class="error-text">{{ $message }}</p>@enderror

            <label for="password">Пароль</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                maxlength="4096"
                required
                @class(['field-error' => $errors->has('password')])
            >
            @error('password')<p class="error-text">{{ $message }}</p>@enderror

            <label class="remember-row" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                <span>Запомнить вход на этом устройстве</span>
            </label>

            <button type="submit" class="primary-button">Войти в панель</button>
        </form>

        <p class="login-note">Попытки входа ограничиваются и записываются в журнал безопасности.</p>
    </section>

    <aside class="login-art" aria-hidden="true">
        <div class="ornament"></div>
        <div class="art-content">
            <span>L2FORGE CMS</span>
            <strong>Управление проектом</strong>
            <p>Новости, настройки, темы и модули будут собраны в единой панели.</p>
        </div>
    </aside>
</main>
@endsection
