<nav class="admin-menu" aria-label="Меню администратора">
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">
        <span>Главная</span>
    </a>

    <p class="admin-menu-title">Контент</p>
    <span class="admin-menu-item disabled" aria-disabled="true">
        <span>Новости</span>
        <small>Скоро</small>
    </span>

    <p class="admin-menu-title">Оформление</p>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.themes.*')]) href="{{ route('admin.themes.index') }}">
        <span>Темы</span>
    </a>

    <p class="admin-menu-title">Система</p>
    <span class="admin-menu-item disabled" aria-disabled="true">
        <span>Настройки</span>
        <small>Скоро</small>
    </span>
    <span class="admin-menu-item disabled" aria-disabled="true">
        <span>Модули</span>
        <small>Скоро</small>
    </span>
    <span class="admin-menu-item disabled" aria-disabled="true">
        <span>Администраторы</span>
        <small>Скоро</small>
    </span>
    <span class="admin-menu-item disabled" aria-disabled="true">
        <span>Журнал действий</span>
        <small>Скоро</small>
    </span>
</nav>
