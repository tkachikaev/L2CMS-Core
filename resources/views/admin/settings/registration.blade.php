@extends('admin.layouts.panel')

@section('title', 'Настройки')
@section('description', 'Регистрация пользователей публичного сайта.')

@section('content')
@include('admin.settings._tabs')

<form class="settings-form" method="POST" action="{{ route('admin.settings.registration.update') }}">
    @csrf
    @method('PUT')

    <section class="form-card settings-narrow-card">
        <div class="settings-card-heading">
            <div>
                <h2>Регистрация пользователей</h2>
                <p>Эти учётные записи относятся только к сайту. Игровые аккаунты Lineage II будут создаваться отдельно.</p>
            </div>
        </div>

        <label class="settings-toggle-row" for="registration_enabled">
            <span>
                <strong>Разрешить регистрацию новых пользователей</strong>
                <small>Показывает кнопку регистрации и открывает страницу создания учётной записи сайта.</small>
            </span>
            <span class="switch-control">
                <input name="registration_enabled" type="hidden" value="0">
                <input id="registration_enabled" name="registration_enabled" type="checkbox" value="1" @checked(old('registration_enabled', $settings['enabled']))>
                <span aria-hidden="true"></span>
            </span>
        </label>

        <label class="settings-toggle-row" for="email_verification_required">
            <span>
                <strong>Требовать подтверждение регистрации по email</strong>
                <small>Пользователь получит доступ к личному кабинету только после перехода по ссылке из письма.</small>
            </span>
            <span class="switch-control">
                <input name="email_verification_required" type="hidden" value="0">
                <input id="email_verification_required" name="email_verification_required" type="checkbox" value="1" @checked(old('email_verification_required', $settings['email_verification_required']))>
                <span aria-hidden="true"></span>
            </span>
        </label>

        @if ($mailReady)
            <div class="notice notice-success settings-inline-notice">
                <p><strong>Почта проверена.</strong> Подтверждение email можно включать.</p>
            </div>
        @else
            <div class="notice notice-warning settings-inline-notice">
                <p><strong>Почта ещё не проверена.</strong> Для включения регистрации с подтверждением email сохраните SMTP-настройки и отправьте тестовое письмо.</p>
                <p><a href="{{ route('admin.settings.mail') }}">Перейти к почтовым настройкам →</a></p>
            </div>
        @endif
    </section>

    <div class="settings-actions settings-actions-narrow">
        <button class="button button-primary" type="submit">Сохранить настройки</button>
    </div>
</form>
@endsection
