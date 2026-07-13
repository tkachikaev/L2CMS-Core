<nav class="admin-menu" aria-label="{{ __('Administrator menu') }}">
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">
        <span>{{ __('Dashboard') }}</span>
    </a>

    <p class="admin-menu-title">{{ __('Content') }}</p>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.news.*')]) href="{{ route('admin.news.index') }}">
        <span>{{ __('News') }}</span>
    </a>

    <p class="admin-menu-title">{{ __('Appearance') }}</p>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.themes.*')]) href="{{ route('admin.themes.index') }}">
        <span>{{ __('Themes') }}</span>
    </a>

    <p class="admin-menu-title">{{ __('System') }}</p>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.*')]) href="{{ route('admin.settings.general') }}">
        <span>{{ __('Settings') }}</span>
    </a>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.users.*')]) href="{{ route('admin.users.index') }}">
        <span>{{ __('Users') }}</span>
    </a>
    <span class="admin-menu-item disabled" aria-disabled="true">
        <span>{{ __('Modules') }}</span>
        <small>{{ __('Coming soon') }}</small>
    </span>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.administrators.*')]) href="{{ route('admin.administrators.index') }}">
        <span>{{ __('Administrators') }}</span>
    </a>
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.logs.*')]) href="{{ route('admin.logs.index') }}">
        <span>{{ __('Audit log') }}</span>
    </a>
</nav>
