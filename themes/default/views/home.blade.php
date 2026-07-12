@extends('theme::layouts.app')

@php
    $heroDetails = $server
        ? array_values(array_filter([
            $server['show_chronicle'] ? $server['chronicle'] : null,
            $server['show_rates'] ? $server['rates'] : null,
        ], static fn ($value) => is_string($value) && $value !== ''))
        : [];
@endphp

@section('title', site_name().($server && $server['show_chronicle'] ? ' — '.$server['chronicle'] : ''))

@section('content')
<section class="hero">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <p class="eyebrow">КЛАССИЧЕСКИЙ МИР · ТВОЯ ЛЕГЕНДА</p>
        <h1>LINEAGE II</h1>
        @if ($heroDetails !== [])
            <h2>{{ implode(' · ', $heroDetails) }}</h2>
        @endif
        @if (site_description() !== '')
            <p class="hero-copy">{{ site_description() }}</p>
        @endif
        <div class="hero-actions">
            @auth
                <a class="button button-gold button-large" href="{{ route('account') }}">Личный кабинет</a>
            @elseif (registration_available())
                <a class="button button-gold button-large" href="{{ route('register') }}">Регистрация</a>
            @else
                <a class="button button-gold button-large" href="{{ route('login') }}">Войти</a>
            @endauth
            <a class="button button-ghost button-large" href="{{ route('downloads') }}">Скачать клиент</a>
        </div>
    </div>
</section>

<section class="container dashboard">
    @if ($servers !== [])
        <article class="panel server-panel">
            <div class="panel-title">
                <h2>{{ count($servers) > 1 ? 'Игровые серверы' : 'Статус сервера' }}</h2>
            </div>

            <div class="server-list">
                @foreach ($servers as $currentServer)
                    <div class="server-row">
                        <div class="server-summary">
                            <strong>{{ $currentServer['name'] }}</strong>
                            <span class="status {{ $currentServer['online'] ? 'online' : 'offline' }}">
                                {{ $currentServer['online'] ? 'Онлайн' : 'Офлайн' }}
                            </span>
                            <small>{{ number_format($currentServer['players'], 0, '.', ' ') }} / {{ number_format($currentServer['max_players'], 0, '.', ' ') }} игроков</small>
                        </div>

                        <div class="progress" aria-label="Заполнение сервера">
                            <span style="width: {{ min(100, ($currentServer['players'] / max(1, $currentServer['max_players'])) * 100) }}%"></span>
                        </div>

                        @if ($currentServer['show_chronicle'] || $currentServer['show_rates'] || $currentServer['show_mode'])
                            <dl>
                                @if ($currentServer['show_chronicle'])
                                    <div><dt>Хроники</dt><dd>{{ $currentServer['chronicle'] }}</dd></div>
                                @endif
                                @if ($currentServer['show_rates'])
                                    <div><dt>Рейты</dt><dd>{{ $currentServer['rates'] }}</dd></div>
                                @endif
                                @if ($currentServer['show_mode'])
                                    <div><dt>Режим</dt><dd>{{ $currentServer['mode'] }}</dd></div>
                                @endif
                            </dl>
                        @endif
                    </div>
                @endforeach
            </div>
        </article>
    @endif

    <div class="content-grid">
        <section class="panel news-panel">
            <div class="panel-title"><h2>Последние новости</h2><a href="{{ route('news.index') }}">Все новости →</a></div>
            <div class="news-list">
                @forelse($news as $item)
                    <article class="news-item">
                        <a class="news-thumb" href="{{ route('news.show', $item) }}" aria-label="{{ $item->title }}">
                            @if ($item->coverUrl())
                                <img src="{{ $item->coverUrl() }}" alt="">
                            @endif
                        </a>
                        <div><time>{{ $item->published_at?->format('d.m.Y') }}</time><h3><a href="{{ route('news.show', $item) }}">{{ $item->title }}</a></h3><p>{{ $item->excerpt }}</p></div>
                    </article>
                @empty
                    <p class="empty">Новостей пока нет.</p>
                @endforelse
            </div>
        </section>

        <section class="panel features-panel">
            <div class="panel-title"><h2>Особенности сервера</h2></div>
            <div class="features-grid">
                @foreach([['⚔','Честная игра','Никаких платных преимуществ'],['◆','Стабильная работа','Изолированная инфраструктура и резервные копии'],['♜','Классический мир','Знакомая механика без лишних изменений'],['◎','Живое сообщество','Открытая разработка и прозрачные правила'],['✦','Регулярные события','Новый контент без превращения игры в магазин'],['⛨','Безопасность','Современная авторизация и аудит действий']] as [$icon,$title,$text])
                <article><span class="feature-icon">{{ $icon }}</span><div><h3>{{ $title }}</h3><p>{{ $text }}</p></div></article>
                @endforeach
            </div>
        </section>

        <aside class="side-column">
            <section class="panel login-panel">
                <div class="panel-title"><h2>{{ auth()->check() ? 'Личный кабинет' : 'Авторизация' }}</h2></div>
                @auth
                    <div class="login-panel-user">
                        <span>Вы вошли как</span>
                        <strong>{{ auth()->user()->name }}</strong>
                        <a class="button button-gold" href="{{ route('account') }}">Открыть кабинет</a>
                    </div>
                @else
                    <form method="POST" action="{{ route('login.store') }}">
                        @csrf
                        <label><span>Логин или email</span><input name="login" required autocomplete="username" placeholder="user@example.com"></label>
                        <label><span>Пароль</span><input name="password" required type="password" autocomplete="current-password" placeholder="••••••••"></label>
                        <button class="button button-gold" type="submit">Войти</button>
                        <p>
                            <a href="{{ route('password.request') }}">Забыли пароль?</a>
                            @if (registration_available())
                                · <a href="{{ route('register') }}">Регистрация</a>
                            @endif
                        </p>
                    </form>
                @endauth
            </section>
            <section id="rating" class="panel rating-panel"><div class="panel-title"><h2>Топ персонажей</h2></div><table><thead><tr><th>#</th><th>Персонаж</th><th>Класс</th><th>Уровень</th></tr></thead><tbody>@foreach($topCharacters as $character)<tr><td>{{ $loop->iteration }}</td><td>{{ $character['name'] }}</td><td>{{ $character['class'] }}</td><td>{{ $character['level'] }}</td></tr>@endforeach</tbody></table></section>
        </aside>
    </div>
</section>
@endsection
