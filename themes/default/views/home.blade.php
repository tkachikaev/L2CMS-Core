@extends('theme::layouts.app')
@section('title', config('app.name').' — '.config('cms.server.chronicle'))
@section('content')
<section class="hero">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <p class="eyebrow">КЛАССИЧЕСКИЙ МИР · ТВОЯ ЛЕГЕНДА</p>
        <h1>LINEAGE II</h1>
        <h2>{{ config('cms.server.chronicle') }} · {{ config('cms.server.rates') }}</h2>
        <p class="hero-copy">Мир осад, клановых войн и больших приключений. Без доната, платных преимуществ и скрытых условий.</p>
        <div class="hero-actions"><a class="button button-gold button-large" href="{{ route('register') }}">Начать игру</a><a class="button button-ghost button-large" href="{{ route('downloads') }}">Скачать клиент</a></div>
    </div>
</section>

<section class="container dashboard">
    <article class="panel server-panel">
        <div class="panel-title"><h2>Статус сервера</h2><span class="status {{ $server['online'] ? 'online' : 'offline' }}">{{ $server['online'] ? 'Онлайн' : 'Офлайн' }}</span></div>
        <div class="server-row"><div><strong>{{ $server['name'] }}</strong><span>{{ number_format($server['players'], 0, '.', ' ') }} / {{ number_format($server['max_players'], 0, '.', ' ') }}</span></div><div class="progress"><span style="width: {{ min(100, ($server['players'] / max(1, $server['max_players'])) * 100) }}%"></span></div><dl><div><dt>Версия</dt><dd>{{ $server['chronicle'] }}</dd></div><div><dt>Рейты</dt><dd>{{ $server['rates'] }}</dd></div><div><dt>Режим</dt><dd>{{ $server['mode'] }}</dd></div></dl></div>
    </article>

    <div class="content-grid">
        <section class="panel news-panel">
            <div class="panel-title"><h2>Последние новости</h2><a href="{{ route('news.index') }}">Все новости →</a></div>
            <div class="news-list">
                @forelse($news as $item)
                    <article class="news-item"><div class="news-thumb"></div><div><time>{{ $item->published_at?->format('d.m.Y') }}</time><h3><a href="{{ route('news.show', $item) }}">{{ $item->title }}</a></h3><p>{{ $item->excerpt }}</p></div></article>
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
            <section class="panel login-panel"><div class="panel-title"><h2>Авторизация</h2></div><form method="get" action="{{ route('login') }}"><label><span>Логин</span><input disabled placeholder="Игровой аккаунт"></label><label><span>Пароль</span><input disabled type="password" placeholder="••••••••"></label><button class="button button-gold" type="submit">Перейти ко входу</button><p>Нет аккаунта? <a href="{{ route('register') }}">Регистрация</a></p></form></section>
            <section id="rating" class="panel rating-panel"><div class="panel-title"><h2>Топ персонажей</h2></div><table><thead><tr><th>#</th><th>Персонаж</th><th>Класс</th><th>Уровень</th></tr></thead><tbody>@foreach($topCharacters as $character)<tr><td>{{ $loop->iteration }}</td><td>{{ $character['name'] }}</td><td>{{ $character['class'] }}</td><td>{{ $character['level'] }}</td></tr>@endforeach</tbody></table></section>
        </aside>
    </div>
</section>
@endsection
