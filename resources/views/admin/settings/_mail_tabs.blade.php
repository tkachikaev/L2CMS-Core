<nav class="mail-template-tabs" aria-label="Разделы почты">
    <a @class(['mail-template-tab', 'active' => request()->routeIs('admin.settings.mail')]) href="{{ route('admin.settings.mail') }}">
        Подключение
    </a>

    @foreach ($mailTemplates as $templateKey => $item)
        <a
            @class([
                'mail-template-tab',
                'active' => request()->routeIs('admin.settings.mail.template') && request()->route('template') === $templateKey,
            ])
            href="{{ route('admin.settings.mail.template', ['template' => $templateKey]) }}"
        >
            {{ $item['title'] }}
        </a>
    @endforeach
</nav>
