<nav class="settings-tabs" aria-label="{{ __('Settings sections') }}">
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.general')]) href="{{ route('admin.settings.general') }}">
        {{ __('General') }}
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.game-server')]) href="{{ route('admin.settings.game-server') }}">
        {{ __('Game servers') }}
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.login-server')]) href="{{ route('admin.settings.login-server') }}">
        {{ __('LoginServers') }}
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.registration')]) href="{{ route('admin.settings.registration') }}">
        {{ __('Registration') }}
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.mail*')]) href="{{ route('admin.settings.mail') }}">
        {{ __('Mail') }}
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.languages')]) href="{{ route('admin.settings.languages') }}">
        {{ __('Languages') }}
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.security*')]) href="{{ route('admin.settings.security') }}">
        {{ __('Security') }}
    </a>
    <a @class(['settings-tab', 'active' => request()->routeIs('admin.settings.system')]) href="{{ route('admin.settings.system') }}">
        {{ __('System') }}
    </a>
</nav>
