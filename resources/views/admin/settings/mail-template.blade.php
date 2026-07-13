@extends('admin.layouts.panel')

@section('title', 'Настройки почты')
@section('description', 'Готовые шаблоны системных писем с безопасным редактированием текста.')

@section('content')
@include('admin.settings._tabs')
@include('admin.settings._mail_tabs')

<div class="mail-template-heading">
    <div>
        <div class="mail-template-title-row">
            <h2>{{ $template['title'] }}</h2>
            @if ($template['customized'])
                <span class="status-badge status-badge-success">Изменён</span>
            @else
                <span class="status-badge status-badge-muted">Стандартный</span>
            @endif
        </div>
        <p>{{ $template['description'] }}</p>
    </div>

    <form
        method="POST"
        action="{{ route('admin.settings.mail.template.reset', ['template' => $template['key']]) }}"
        data-mail-template-reset
    >
        @csrf
        <button class="button button-secondary" type="submit">Восстановить стандартный шаблон</button>
    </form>
</div>

<div class="mail-template-layout" data-mail-template-editor data-requires-action="{{ $template['requires_action'] ? '1' : '0' }}">
    <form class="settings-form mail-template-form" method="POST" action="{{ route('admin.settings.mail.template.update', ['template' => $template['key']]) }}">
        @csrf
        @method('PUT')

        <section class="form-card">
            <h2>Содержание письма</h2>
            <p class="mail-template-help">HTML вводить не требуется: адаптивное оформление формирует CMS. Для нового абзаца оставьте пустую строку.</p>

            <div class="form-group">
                <label for="subject">Тема письма</label>
                <input id="subject" name="subject" type="text" maxlength="200" required value="{{ old('subject', $template['subject']) }}" data-mail-template-field="subject">
            </div>

            <div class="form-group">
                <label for="heading">Заголовок</label>
                <input id="heading" name="heading" type="text" maxlength="150" required value="{{ old('heading', $template['heading']) }}" data-mail-template-field="heading">
            </div>

            <div class="form-group">
                <label for="body">Основной текст</label>
                <textarea id="body" name="body" rows="8" maxlength="5000" required data-mail-template-field="body">{{ old('body', $template['body']) }}</textarea>
                <small>Можно использовать <code>**жирный текст**</code> и <code>*курсив*</code>. HTML-теги блокируются.</small>
            </div>

            @if ($template['requires_action'])
                <div class="form-group">
                    <label for="action_text">Текст кнопки</label>
                    <input id="action_text" name="action_text" type="text" maxlength="100" required value="{{ old('action_text', $template['action_text']) }}" data-mail-template-field="action_text">
                    <small>Адрес кнопки создаётся CMS автоматически и не редактируется.</small>
                </div>
            @else
                <input type="hidden" name="action_text" value="">
            @endif

            <div class="form-group">
                <label for="footer">Дополнительный текст</label>
                <textarea id="footer" name="footer" rows="5" maxlength="3000" data-mail-template-field="footer">{{ old('footer', $template['footer']) }}</textarea>
                <small>Обычно здесь указывают срок действия ссылки или пояснение по безопасности.</small>
            </div>
        </section>

        <section class="form-card mail-template-variables">
            <h2>Доступные переменные</h2>
            <p>Нажмите переменную, чтобы вставить её в последнее выбранное поле.</p>
            <div class="mail-template-variable-list">
                @foreach ($template['variables'] as $variable)
                    <button class="mail-template-variable" type="button" data-template-variable="{{ $variable }}">{{ '{{'.$variable.'}}' }}</button>
                @endforeach
            </div>
        </section>

        <div class="settings-actions settings-actions-inside">
            <button class="button button-primary" type="submit">Сохранить шаблон</button>
        </div>
    </form>

    <aside class="mail-template-side">
        <section class="form-card mail-preview-card">
            <div class="mail-preview-heading">
                <div>
                    <h2>Предпросмотр</h2>
                    <p>Тестовые значения подставляются только для отображения.</p>
                </div>
                <span class="status-badge status-badge-muted">Email</span>
            </div>

            <div class="mail-preview-subject">
                <span>Тема</span>
                <strong data-mail-preview="subject">{{ $preview['subject'] }}</strong>
            </div>

            <div class="mail-preview-window">
                <div class="mail-preview-brand">{{ site_name() }}</div>
                <div class="mail-preview-content">
                    <h3 data-mail-preview="heading">{{ $preview['heading'] }}</h3>
                    <div class="mail-preview-copy" data-mail-preview="body">{{ $preview['body'] }}</div>

                    @if ($template['requires_action'])
                        <span class="mail-preview-button" data-mail-preview="action_text">{{ $preview['action_text'] }}</span>
                    @endif

                    <div class="mail-preview-footer" data-mail-preview="footer">{{ $preview['footer'] }}</div>
                    <div class="mail-preview-signature">С уважением, команда {{ site_name() }}</div>
                </div>
            </div>
        </section>

        <form class="settings-form" method="POST" action="{{ route('admin.settings.mail.template.test', ['template' => $template['key']]) }}">
            @csrf
            <section class="form-card mail-template-test-card">
                <h2>Тестовая отправка</h2>
                <p>Отправляется сохранённая версия шаблона с демонстрационными данными и безопасной примерной ссылкой.</p>

                @if (! $mailSettings['ready'])
                    <div class="notice notice-warning">
                        <p>Сначала проверьте SMTP во вкладке «Подключение».</p>
                    </div>
                @endif

                <div class="form-group">
                    <label for="test_email">Адрес получателя</label>
                    <input id="test_email" name="test_email" type="email" maxlength="255" required value="{{ old('test_email', $mailSettings['admin_email']) }}" placeholder="admin@example.com">
                </div>

                <button class="button button-secondary" type="submit" @disabled(! $mailSettings['ready'])>Отправить тестовый шаблон</button>
            </section>
        </form>
    </aside>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/mail-templates.js') }}?v={{ cms_version() }}" defer></script>
@endpush
