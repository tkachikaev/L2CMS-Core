<nav class="admin-menu" aria-label="{{ __('Administrator menu') }}">
    <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">
        <span>{{ __('Dashboard') }}</span>
    </a>

    <details @class(['admin-menu-group', 'active' => request()->routeIs('admin.news.*', 'admin.pages.*')]) data-admin-menu-group="content" @if (request()->routeIs('admin.news.*', 'admin.pages.*')) open @endif>
        <summary class="admin-menu-group-summary">
            <span>{{ __('Content') }}</span>
            <span class="admin-menu-group-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="admin-menu-group-items">
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.news.*')]) href="{{ route('admin.news.index') }}">
                <span>{{ __('News') }}</span>
            </a>
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.pages.*')]) href="{{ route('admin.pages.index') }}">
                <span>{{ __('Pages') }}</span>
            </a>
        </div>
    </details>

    <details @class(['admin-menu-group', 'active' => request()->routeIs('admin.settings.general*', 'admin.settings.languages*', 'admin.themes.*')]) data-admin-menu-group="site" @if (request()->routeIs('admin.settings.general*', 'admin.settings.languages*', 'admin.themes.*')) open @endif>
        <summary class="admin-menu-group-summary">
            <span>{{ __('Site') }}</span>
            <span class="admin-menu-group-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="admin-menu-group-items">
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.general*')]) href="{{ route('admin.settings.general') }}">
                <span>{{ __('Main settings') }}</span>
            </a>
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.languages*')]) href="{{ route('admin.settings.languages') }}">
                <span>{{ __('Languages') }}</span>
            </a>
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.themes.*')]) href="{{ route('admin.themes.index') }}">
                <span>{{ __('Themes') }}</span>
            </a>
        </div>
    </details>

    <details @class(['admin-menu-group', 'active' => request()->routeIs('admin.settings.game-server*', 'admin.settings.login-server*', 'admin.settings.game-accounts*')]) data-admin-menu-group="servers" @if (request()->routeIs('admin.settings.game-server*', 'admin.settings.login-server*', 'admin.settings.game-accounts*')) open @endif>
        <summary class="admin-menu-group-summary">
            <span>{{ __('Servers') }}</span>
            <span class="admin-menu-group-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="admin-menu-group-items">
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.game-server*')]) href="{{ route('admin.settings.game-server') }}">
                <span>{{ __('Game servers') }}</span>
            </a>
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.login-server*')]) href="{{ route('admin.settings.login-server') }}">
                <span>{{ __('Login servers') }}</span>
            </a>
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.game-accounts*')]) href="{{ route('admin.settings.game-accounts') }}">
                <span>{{ __('Game accounts') }}</span>
            </a>
        </div>
    </details>

    <details @class(['admin-menu-group', 'active' => request()->routeIs('admin.users.*', 'admin.settings.registration*')]) data-admin-menu-group="users" @if (request()->routeIs('admin.users.*', 'admin.settings.registration*')) open @endif>
        <summary class="admin-menu-group-summary">
            <span>{{ __('Users') }}</span>
            <span class="admin-menu-group-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="admin-menu-group-items">
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.users.*')]) href="{{ route('admin.users.index') }}">
                <span>{{ __('Users') }}</span>
            </a>
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.registration*')]) href="{{ route('admin.settings.registration') }}">
                <span>{{ __('Registration') }}</span>
            </a>
        </div>
    </details>

    <details @class(['admin-menu-group', 'active' => request()->routeIs('admin.settings.mail*', 'admin.settings.security*', 'admin.settings.system', 'admin.administrators.*', 'admin.logs.*')]) data-admin-menu-group="system" @if (request()->routeIs('admin.settings.mail*', 'admin.settings.security*', 'admin.settings.system', 'admin.administrators.*', 'admin.logs.*')) open @endif>
        <summary class="admin-menu-group-summary">
            <span>{{ __('System') }}</span>
            <span class="admin-menu-group-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="admin-menu-group-items">
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.mail*')]) href="{{ route('admin.settings.mail') }}">
                <span>{{ __('Mail') }}</span>
            </a>
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.security*')]) href="{{ route('admin.settings.security') }}">
                <span>{{ __('Security') }}</span>
            </a>
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.settings.system')]) href="{{ route('admin.settings.system') }}">
                <span>{{ __('System information') }}</span>
            </a>
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.administrators.*')]) href="{{ route('admin.administrators.index') }}">
                <span>{{ __('Administrators') }}</span>
            </a>
            <a @class(['admin-menu-item', 'active' => request()->routeIs('admin.logs.*')]) href="{{ route('admin.logs.index') }}">
                <span>{{ __('Audit log') }}</span>
            </a>
            <span class="admin-menu-item disabled" aria-disabled="true">
                <span>{{ __('Modules') }}</span>
                <small>{{ __('Coming soon') }}</small>
            </span>
        </div>
    </details>
</nav>
