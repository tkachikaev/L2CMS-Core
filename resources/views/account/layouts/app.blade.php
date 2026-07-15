<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ locale_direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#10151d">
    @if (site_favicon_url())
        <link rel="icon" href="{{ site_favicon_url() }}">
    @endif
    <title>@yield('title', __('Personal account')) — {{ site_name() }}</title>
    <link rel="stylesheet" href="{{ asset('assets/account/css/app.css') }}?v={{ cms_version() }}">
</head>
<body class="account-body">
<div class="account-shell">
    <aside class="account-sidebar">
        <a class="account-brand" href="{{ public_route('account') }}">
            @if (site_logo_url())
                <img src="{{ site_logo_url() }}" alt="{{ site_name() }}">
            @else
                <span class="account-brand-mark">L2</span>
            @endif
            <span><strong>{{ site_name() }}</strong><small>{{ __('Player account') }}</small></span>
        </a>

        <nav class="account-nav" aria-label="{{ __('Player account navigation') }}">
            <a @class(['active' => request()->routeIs('account', 'localized.account')]) href="{{ public_route('account') }}">
                <span aria-hidden="true">⌂</span>{{ __('Overview') }}
            </a>
            <a @class(['active' => request()->routeIs('game-accounts.*', 'localized.game-accounts.*')]) href="{{ public_route('account') }}#game-accounts">
                <span aria-hidden="true">▣</span>{{ __('Game accounts') }}
            </a>
        </nav>

        <div class="account-sidebar-footer">
            <a href="{{ public_route('home') }}">← {{ __('Back to website') }}</a>
            <span>{{ __('Version :version', ['version' => cms_version()]) }}</span>
        </div>
    </aside>

    <main class="account-main">
        <header class="account-topbar">
            <div><span>{{ __('Player account') }}</span><strong>{{ $user->name }}</strong></div>
            <form method="POST" action="{{ public_route('logout') }}">
                @csrf
                <button type="submit">{{ __('Sign out') }}</button>
            </form>
        </header>

        <div class="account-content">
            @if (session('status'))
                <div class="account-notice success" role="status">{{ session('status') }}</div>
            @endif
            @if (session('warning'))
                <div class="account-notice warning" role="alert">{{ session('warning') }}</div>
            @endif
            @if ($errors->any())
                <div class="account-notice error" role="alert">
                    @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
