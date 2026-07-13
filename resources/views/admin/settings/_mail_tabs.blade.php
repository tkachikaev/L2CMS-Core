<nav class="mail-template-tabs" aria-label="{{ __('Mail sections') }}">
    <a @class(['mail-template-tab', 'active' => request()->routeIs('admin.settings.mail')]) href="{{ route('admin.settings.mail') }}">
        {{ __('Connection') }}
    </a>

    @foreach ($mailTemplates as $templateKey => $item)
        <a
            @class([
                'mail-template-tab',
                'active' => request()->routeIs('admin.settings.mail.template') && request()->route('template') === $templateKey,
            ])
            href="{{ route('admin.settings.mail.template', ['template' => $templateKey, 'locale' => $templateLocale ?? app()->getLocale()]) }}"
        >
            {{ $item['title'] }}
        </a>
    @endforeach
</nav>
