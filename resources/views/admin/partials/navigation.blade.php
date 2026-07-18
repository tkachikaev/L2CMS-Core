<nav class="admin-menu" wire:navigate:scroll aria-label="{{ __('Administrator menu') }}">
    <a wire:navigate.hover wire:current.exact="active" class="admin-menu-item" href="{{ route('admin.dashboard') }}">
        <span>{{ __('Dashboard') }}</span>
    </a>

    <details class="admin-menu-group" data-admin-menu-group="content" @if (request()->routeIs('admin.news.*', 'admin.pages.*')) open @endif>
        <summary class="admin-menu-group-summary">
            <span>{{ __('Content') }}</span>
            <span class="admin-menu-group-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="admin-menu-group-items">
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.news.index') }}">
                <span>{{ __('News') }}</span>
            </a>
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.pages.index') }}">
                <span>{{ __('Pages') }}</span>
            </a>
        </div>
    </details>

    <details class="admin-menu-group" data-admin-menu-group="site" @if (request()->routeIs('admin.settings.general*', 'admin.settings.languages*', 'admin.themes.*', 'admin.account-themes.*')) open @endif>
        <summary class="admin-menu-group-summary">
            <span>{{ __('Site') }}</span>
            <span class="admin-menu-group-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="admin-menu-group-items">
            <a wire:navigate.hover wire:current.exact="active" class="admin-menu-item" href="{{ route('admin.settings.general') }}">
                <span>{{ __('Main settings') }}</span>
            </a>
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.settings.languages') }}">
                <span>{{ __('Languages') }}</span>
            </a>
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.themes.index') }}">
                <span>{{ __('Themes') }}</span>
            </a>
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.account-themes.index') }}">
                <span>{{ __('Player account themes') }}</span>
            </a>
        </div>
    </details>

    <details class="admin-menu-group" data-admin-menu-group="servers" @if (request()->routeIs('admin.settings.game-server*', 'admin.settings.login-server*', 'admin.settings.game-accounts*')) open @endif>
        <summary class="admin-menu-group-summary">
            <span>{{ __('Servers') }}</span>
            <span class="admin-menu-group-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="admin-menu-group-items">
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.settings.game-server') }}">
                <span>{{ __('Game servers') }}</span>
            </a>
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.settings.login-server') }}">
                <span>{{ __('Login servers') }}</span>
            </a>
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.settings.game-accounts') }}">
                <span>{{ __('Game accounts') }}</span>
            </a>
        </div>
    </details>

    <details class="admin-menu-group" data-admin-menu-group="users" @if (request()->routeIs('admin.users.*', 'admin.settings.registration*')) open @endif>
        <summary class="admin-menu-group-summary">
            <span>{{ __('Users') }}</span>
            <span class="admin-menu-group-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="admin-menu-group-items">
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.users.index') }}">
                <span>{{ __('Users') }}</span>
            </a>
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.settings.registration') }}">
                <span>{{ __('Registration') }}</span>
            </a>
        </div>
    </details>

    <details class="admin-menu-group" data-admin-menu-group="system" @if (request()->routeIs('admin.settings.mail*', 'admin.settings.security*', 'admin.settings.system', 'admin.administrators.*', 'admin.logs.*')) open @endif>
        <summary class="admin-menu-group-summary">
            <span>{{ __('System') }}</span>
            <span class="admin-menu-group-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="admin-menu-group-items">
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.settings.mail') }}">
                <span>{{ __('Mail') }}</span>
            </a>
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.settings.security') }}">
                <span>{{ __('Security') }}</span>
            </a>
            <a wire:navigate.hover wire:current.exact="active" class="admin-menu-item" href="{{ route('admin.settings.system') }}">
                <span>{{ __('System information') }}</span>
            </a>
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.administrators.index') }}">
                <span>{{ __('Administrators') }}</span>
            </a>
            <a wire:navigate.hover wire:current="active" class="admin-menu-item" href="{{ route('admin.logs.index') }}">
                <span>{{ __('Audit log') }}</span>
            </a>
            <span class="admin-menu-item disabled" aria-disabled="true">
                <span>{{ __('Modules') }}</span>
                <small>{{ __('Coming soon') }}</small>
            </span>
        </div>
    </details>
</nav>
