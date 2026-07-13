@extends('admin.layouts.panel')

@section('title', 'Настройки')
@section('description', 'Версии, окружение и состояние компонентов L2Forge CMS.')

@section('content')
@include('admin.settings._tabs')

<section class="system-overview">
    <div>
        <span class="system-eyebrow">L2Forge CMS</span>
        <strong>Версия {{ $system['cms']['version'] }}</strong>
        <p>Версия берётся из файла <code>VERSION</code> в корне проекта.</p>
    </div>

    <div class="system-overview-actions">
        <a class="button button-secondary" href="{{ route('admin.settings.system') }}">Обновить сведения</a>
        <button class="button button-primary" type="button" data-copy-system-report>Скопировать отчёт</button>
    </div>
</section>

<div class="system-information-grid">
    <section class="form-card system-card">
        <h2>Программное обеспечение</h2>
        <dl class="system-definition-list">
            <div><dt>PHP</dt><dd>{{ $system['software']['php'] }}</dd></div>
            <div><dt>Laravel</dt><dd>{{ $system['software']['laravel'] }}</dd></div>
            <div><dt>Composer</dt><dd>{{ $system['software']['composer'] }}</dd></div>
            <div><dt>Операционная система</dt><dd>{{ $system['software']['os'] }}</dd></div>
            <div><dt>Архитектура PHP</dt><dd>{{ $system['software']['architecture'] }}</dd></div>
            <div><dt>PHP SAPI</dt><dd><code>{{ $system['software']['sapi'] }}</code></dd></div>
        </dl>
    </section>

    <section class="form-card system-card">
        <h2>Окружение Laravel</h2>
        <dl class="system-definition-list">
            <div><dt>Среда</dt><dd><code>{{ $system['environment']['name'] }}</code></dd></div>
            <div>
                <dt>Режим отладки</dt>
                <dd>
                    <span class="status-badge {{ $system['environment']['debug'] ? 'status-badge-warning' : 'status-badge-success' }}">
                        {{ $system['environment']['debug'] ? 'Включён' : 'Выключен' }}
                    </span>
                </dd>
            </div>
            <div><dt>Часовой пояс PHP</dt><dd>{{ $system['environment']['php_timezone'] }}</dd></div>
            <div><dt>Часовой пояс CMS</dt><dd>{{ $system['environment']['cms_timezone'] }}</dd></div>
            <div><dt>Кэш</dt><dd><code>{{ $system['environment']['cache'] }}</code></dd></div>
            <div><dt>Сессии</dt><dd><code>{{ $system['environment']['session'] }}</code></dd></div>
            <div><dt>Очереди</dt><dd><code>{{ $system['environment']['queue'] }}</code></dd></div>
            <div><dt>Почта</dt><dd><code>{{ $system['environment']['mail'] }}</code></dd></div>
            <div><dt>Логи Laravel</dt><dd><code>{{ $system['environment']['logging'] }}</code></dd></div>
        </dl>
    </section>

    <section class="form-card system-card">
        <h2>База данных CMS</h2>
        <dl class="system-definition-list">
            <div><dt>Подключение</dt><dd><code>{{ $system['database']['connection'] }}</code></dd></div>
            <div><dt>Драйвер</dt><dd>{{ $system['database']['driver_label'] }}</dd></div>
            <div><dt>Версия сервера</dt><dd>{{ $system['database']['version'] ?: 'Не удалось определить' }}</dd></div>
            <div>
                <dt>Состояние</dt>
                <dd>
                    <span class="status-badge {{ $system['database']['connected'] ? 'status-badge-success' : 'status-badge-danger' }}">
                        {{ $system['database']['connected'] ? 'Подключено' : 'Ошибка подключения' }}
                    </span>
                </dd>
            </div>
            @if ($system['database']['path'])
                <div><dt>Файл</dt><dd><code>{{ $system['database']['path'] }}</code></dd></div>
            @endif
            @if ($system['database']['size'])
                <div><dt>Размер</dt><dd>{{ $system['database']['size'] }}</dd></div>
            @endif
        </dl>
    </section>
</div>

<section class="form-card system-components-card">
    <div class="system-section-heading">
        <div>
            <h2>Состояние компонентов</h2>
            <p>Проверки выполняются заново при каждом открытии страницы.</p>
        </div>
    </div>

    <div class="system-component-list">
        @foreach ($system['components'] as $component)
            <article class="system-component-row">
                <span @class([
                    'system-status-dot',
                    'success' => $component['state'] === 'success',
                    'warning' => $component['state'] === 'warning',
                    'danger' => $component['state'] === 'danger',
                    'neutral' => $component['state'] === 'neutral',
                ]) aria-hidden="true"></span>
                <div>
                    <strong>{{ $component['label'] }}</strong>
                    <small>{{ $component['details'] }}</small>
                </div>
                <span @class([
                    'status-badge',
                    'status-badge-success' => $component['state'] === 'success',
                    'status-badge-warning' => $component['state'] === 'warning',
                    'status-badge-danger' => $component['state'] === 'danger',
                    'status-badge-muted' => $component['state'] === 'neutral',
                ])>{{ $component['status'] }}</span>
            </article>
        @endforeach
    </div>
</section>

<section class="form-card system-extensions-card">
    <div class="system-section-heading">
        <div>
            <h2>Расширения PHP</h2>
            <p>Обязательные расширения проверяются также скриптами установки и диагностики.</p>
        </div>
    </div>

    <div class="system-extension-grid">
        @foreach ($system['extensions'] as $extension)
            <div @class(['system-extension', 'missing' => ! $extension['loaded']])>
                <div>
                    <code>{{ $extension['name'] }}</code>
                    <small>{{ $extension['required'] ? 'Обязательное' : 'Необязательное' }}</small>
                </div>
                <span class="status-badge {{ $extension['loaded'] ? 'status-badge-success' : ($extension['required'] ? 'status-badge-danger' : 'status-badge-muted') }}">
                    {{ $extension['loaded'] ? 'Установлено' : 'Не установлено' }}
                </span>
            </div>
        @endforeach
    </div>
</section>

<section class="form-card system-report-card">
    <div class="system-section-heading">
        <div>
            <h2>Безопасный отчёт для поддержки</h2>
            <p>Отчёт не содержит APP_KEY, паролей, токенов, cookies, логинов баз данных и абсолютных путей.</p>
        </div>
        <span class="system-copy-state" data-system-copy-state aria-live="polite"></span>
    </div>

    <pre data-system-report-preview>{{ $system['report'] }}</pre>
    <textarea class="system-report-source" data-system-report readonly aria-hidden="true" tabindex="-1">{{ $system['report'] }}</textarea>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/system.js') }}?v={{ cms_version() }}" defer></script>
@endpush
