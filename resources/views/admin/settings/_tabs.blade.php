<nav class="settings-tabs" aria-label="Разделы настроек">
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.general')]) href="{{ route('admin.settings.general') }}">
        Основные
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.game-server')]) href="{{ route('admin.settings.game-server') }}">
        Игровой сервер
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.login-server')]) href="{{ route('admin.settings.login-server') }}">
        Логин сервер
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.registration')]) href="{{ route('admin.settings.registration') }}">
        Регистрация
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.mail')]) href="{{ route('admin.settings.mail') }}">
        Почта
    </a>
</nav>
