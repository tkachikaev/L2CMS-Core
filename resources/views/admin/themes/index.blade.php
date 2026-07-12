@extends('admin.layouts.panel')

@section('title', 'Темы')
@section('description', 'Оформление публичной части сайта. Дизайн административной панели от темы не зависит.')

@section('content')
<div class="themes-toolbar">
    <div>
        <span>Активная тема</span>
        <strong>{{ $activeThemeSlug }}</strong>
    </div>
    <div>
        <span>Установлено</span>
        <strong>{{ count($themes) }}</strong>
    </div>
    <a class="button button-secondary" href="{{ route('home') }}" target="_blank" rel="noopener">Открыть сайт ↗</a>
</div>

@if ($themes === [])
    <div class="empty-box">Темы не найдены. Проверь каталог <code>themes</code>.</div>
@else
    <div class="themes-list">
        @foreach ($themes as $theme)
            <article @class(['theme-row', 'active' => $theme['active'], 'invalid' => ! $theme['valid'] || ! $theme['compatible']])>
                <div class="theme-thumb">
                    @if ($theme['preview_url'])
                        <img src="{{ $theme['preview_url'] }}" alt="Предпросмотр темы {{ $theme['name'] }}">
                    @else
                        <span aria-hidden="true">L2</span>
                    @endif
                </div>

                <div class="theme-info">
                    <div class="theme-heading">
                        <div>
                            <h2>{{ $theme['name'] }}</h2>
                            <p>{{ $theme['description'] ?: 'Описание темы не указано.' }}</p>
                        </div>
                        @if ($theme['active'])
                            <span class="theme-state active">Активна</span>
                        @elseif ($theme['valid'] && $theme['compatible'])
                            <span class="theme-state ready">Готова</span>
                        @else
                            <span class="theme-state error">Ошибка</span>
                        @endif
                    </div>

                    <div class="theme-meta-line">
                        <span>Версия {{ $theme['version'] }}</span>
                        <span>Автор: {{ $theme['author'] }}</span>
                        <span>Каталог: {{ $theme['slug'] }}</span>
                    </div>

                    @if ($theme['errors'] !== [])
                        <div class="theme-errors">
                            @foreach ($theme['errors'] as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="theme-actions">
                    @if ($theme['active'])
                        <a class="button button-secondary" href="{{ route('home') }}" target="_blank" rel="noopener">Посмотреть</a>
                    @elseif ($theme['valid'] && $theme['compatible'])
                        <form method="POST" action="{{ route('admin.themes.activate', $theme['slug']) }}">
                            @csrf
                            <button class="button button-primary" type="submit">Активировать</button>
                        </form>
                    @else
                        <button class="button button-secondary" type="button" disabled>Недоступна</button>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif
@endsection
