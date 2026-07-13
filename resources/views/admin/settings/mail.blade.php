@extends('admin.layouts.panel')

@section('title', 'Настройки')
@section('description', 'SMTP для подтверждения email и восстановления паролей.')

@section('content')
@include('admin.settings._tabs')
@include('admin.settings._mail_tabs')

<div class="mail-settings-status">
    @if ($settings['password_saved'] && ! $settings['password_valid'])
        <span class="status-badge status-badge-danger">Пароль недоступен</span>
        <span>Сохранённый SMTP-пароль нельзя расшифровать текущим APP_KEY. Введите пароль заново и сохраните настройки.</span>
    @elseif ($settings['ready'])
        <span class="status-badge status-badge-success">Почта проверена</span>
        <span>Последняя успешная проверка: {{ \Illuminate\Support\Carbon::parse($settings['tested_at'])->format('d.m.Y H:i') }}</span>
    @elseif ($settings['configured'])
        <span class="status-badge status-badge-warning">Требуется проверка</span>
        <span>Настройки сохранены, но тестовое письмо ещё не отправлено.</span>
    @else
        <span class="status-badge status-badge-muted">Не настроено</span>
        <span>Заполните параметры SMTP и сохраните форму.</span>
    @endif
</div>

<div class="settings-grid mail-settings-grid">
    <form class="settings-form" method="POST" action="{{ route('admin.settings.mail.update') }}">
        @csrf
        @method('PUT')

        <section class="form-card">
            <h2>Подключение SMTP</h2>

            <div class="form-row form-row-2-1">
                <div class="form-group">
                    <label for="smtp_host">SMTP-сервер</label>
                    <input id="smtp_host" name="smtp_host" type="text" maxlength="255" required value="{{ old('smtp_host', $settings['host']) }}" placeholder="smtp.example.com" autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="smtp_port">Порт</label>
                    <input id="smtp_port" name="smtp_port" type="number" min="1" max="65535" required value="{{ old('smtp_port', $settings['port']) }}" placeholder="587">
                </div>
            </div>

            <div class="form-group">
                <label for="encryption">Защищённое соединение</label>
                <select id="encryption" name="encryption" required>
                    <option value="tls" @selected(old('encryption', $settings['encryption']) === 'tls')>STARTTLS / TLS — обычно порт 587</option>
                    <option value="ssl" @selected(old('encryption', $settings['encryption']) === 'ssl')>SSL / SMTPS — обычно порт 465</option>
                    <option value="none" @selected(old('encryption', $settings['encryption']) === 'none')>Без принудительного шифрования</option>
                </select>
            </div>

            <div class="form-group">
                <label for="smtp_username">Имя пользователя SMTP</label>
                <input id="smtp_username" name="smtp_username" type="text" maxlength="255" value="{{ old('smtp_username', $settings['username']) }}" placeholder="no-reply@example.com" autocomplete="off">
                <small>Можно оставить пустым, если SMTP-сервер не требует авторизацию.</small>
            </div>

            <div class="form-group">
                <label for="smtp_password">Пароль SMTP</label>
                <input id="smtp_password" name="smtp_password" type="password" maxlength="1024" value="" placeholder="{{ $settings['password_saved'] && $settings['password_valid'] ? 'Пароль уже сохранён' : 'Введите пароль' }}" autocomplete="new-password">
                <small>
                    @if ($settings['password_saved'] && $settings['password_valid'])
                        Текущий пароль зашифрован. Оставьте поле пустым, чтобы не менять его.
                    @elseif ($settings['password_saved'])
                        Сохранённый пароль больше нельзя расшифровать. Введите его заново.
                    @else
                        Пароль сохраняется в зашифрованном виде с использованием APP_KEY.
                    @endif
                </small>
            </div>
        </section>

        <section class="form-card">
            <h2>Отправитель</h2>

            <div class="form-group">
                <label for="from_address">Email отправителя</label>
                <input id="from_address" name="from_address" type="email" maxlength="255" required value="{{ old('from_address', $settings['from_address']) }}" placeholder="no-reply@example.com">
            </div>

            <div class="form-group">
                <label for="from_name">Имя отправителя</label>
                <input id="from_name" name="from_name" type="text" maxlength="100" required value="{{ old('from_name', $settings['from_name']) }}" placeholder="{{ site_name() }}">
            </div>

            <div class="form-group">
                <label for="notification_email">Email для системных уведомлений</label>
                <input id="notification_email" name="notification_email" type="email" maxlength="255" value="{{ old('notification_email', $settings['admin_email']) }}" placeholder="admin@example.com">
                <small>Будет использоваться для будущих уведомлений администрации. Это не SMTP-логин.</small>
            </div>
        </section>

        <div class="settings-actions settings-actions-inside">
            <button class="button button-primary" type="submit">Сохранить почтовые настройки</button>
        </div>
    </form>

    <aside>
        <form class="settings-form" method="POST" action="{{ route('admin.settings.mail.test') }}">
            @csrf

            <section class="form-card mail-test-card">
                <h2>Проверка отправки</h2>
                <p>Тест использует уже сохранённые настройки. После любого изменения SMTP-параметров проверку нужно выполнить заново.</p>

                <div class="form-group">
                    <label for="test_email">Адрес для тестового письма</label>
                    <input id="test_email" name="test_email" type="email" maxlength="255" required value="{{ old('test_email', $settings['admin_email']) }}" placeholder="admin@example.com">
                </div>

                <button class="button button-secondary" type="submit" @disabled(! $settings['configured'])>Отправить тестовое письмо</button>
            </section>
        </form>

        <section class="form-card mail-help-card">
            <h2>Что потребуется</h2>
            <ul class="settings-help-list">
                <li>SMTP-сервер и порт почтового провайдера.</li>
                <li>Логин и пароль либо пароль приложения.</li>
                <li>SPF, DKIM и DMARC для домена отправителя.</li>
                <li>Корректный APP_URL для ссылок подтверждения и сброса пароля.</li>
            </ul>
            <p class="muted-admin">Секреты никогда не выводятся обратно в HTML и не должны попадать в журналы.</p>
        </section>
    </aside>
</div>
@endsection
