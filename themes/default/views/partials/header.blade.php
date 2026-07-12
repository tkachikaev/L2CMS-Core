<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ site_name() }} — на главную">
            @if (site_logo_url())
                <img class="brand-logo" src="{{ site_logo_url() }}" alt="{{ site_name() }}">
            @else
                <span class="brand-mark">L2</span>
                <span><strong>{{ site_name() }}</strong><small>LINEAGE II</small></span>
            @endif
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-menu">Меню</button>
        <nav id="main-menu" class="main-nav" aria-label="Основная навигация">
            <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Главная</a>
            <a class="{{ request()->routeIs('news.*') ? 'active' : '' }}" href="{{ route('news.index') }}">Новости</a>
            <a href="{{ route('home') }}#rating">Статистика</a>
            <a class="{{ request()->routeIs('downloads') ? 'active' : '' }}" href="{{ route('downloads') }}">Файлы</a>
            <a class="{{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">О сервере</a>
            @auth
                <a class="mobile-account-link {{ request()->routeIs('account') ? 'active' : '' }}" href="{{ route('account') }}">Кабинет</a>
            @else
                <a class="mobile-account-link {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">Вход</a>
                @if (registration_available())
                    <a class="mobile-account-link {{ request()->routeIs('register') ? 'active' : '' }}" href="{{ route('register') }}">Регистрация</a>
                @endif
            @endauth
        </nav>

        <div class="header-actions">
            @auth
                <a class="button button-gold" href="{{ route('account') }}">Кабинет</a>
                <form class="header-logout-form" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="button button-ghost" type="submit">Выйти</button>
                </form>
            @else
                <a class="button button-ghost" href="{{ route('login') }}">Вход</a>
                @if (registration_available())
                    <a class="button button-gold" href="{{ route('register') }}">Регистрация</a>
                @endif
            @endauth
        </div>
    </div>
</header>
