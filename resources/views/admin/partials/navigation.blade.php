<nav class="admin-menu" aria-label="Меню администратора">
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">
        <span>Главная</span>
    </a>

    <p class="admin-menu-title">Контент</p>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.news.*')]) href="{{ route('admin.news.index') }}">
        <span>Новости</span>
    </a>

    <p class="admin-menu-title">Оформление</p>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.themes.*')]) href="{{ route('admin.themes.index') }}">
        <span>Темы</span>
    </a>

    <p class="admin-menu-title">Система</p>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.*')]) href="{{ route('admin.settings.general') }}">
        <span>Настройки</span>
    </a>
    <span class="admin-menu-item disabled" aria-disabled="true">
        <span>Модули</span>
        <small>Скоро</small>
    </span>
    <span class="admin-menu-item disabled" aria-disabled="true">
        <span>Администраторы</span>
        <small>Скоро</small>
    </span>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.logs.*')]) href="{{ route('admin.logs.index') }}">
        <span>Журнал действий</span>
    </a>
</nav>
