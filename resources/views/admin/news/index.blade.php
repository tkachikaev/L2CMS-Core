@extends('admin.layouts.panel')

@section('title', 'Новости')
@section('description', 'Создание, редактирование и публикация новостей публичного сайта.')

@section('content')
<div class="content-toolbar">
    <div class="content-stat">
        <span>Всего</span>
        <strong>{{ $totalCount }}</strong>
    </div>
    <div class="content-stat">
        <span>Опубликовано</span>
        <strong>{{ $publishedCount }}</strong>
    </div>
    <div class="content-stat">
        <span>Запланировано</span>
        <strong>{{ $scheduledCount }}</strong>
    </div>
    <div class="content-stat">
        <span>Черновики</span>
        <strong>{{ $draftCount }}</strong>
    </div>
    <a class="button button-primary" href="{{ route('admin.news.create') }}">Создать новость</a>
</div>

@if ($news->isEmpty())
    <div class="empty-state">
        <div class="empty-state-mark" aria-hidden="true">N</div>
        <h2>Новостей пока нет</h2>
        <p>Создайте первую новость. Она появится на сайте после публикации.</p>
        <a class="button button-primary" href="{{ route('admin.news.create') }}">Создать первую новость</a>
    </div>
@else
    <div class="content-list">
        @foreach ($news as $item)
            <article class="content-row">
                <div class="content-row-main">
                    <a class="content-row-title" href="{{ route('admin.news.edit', $item) }}">{{ $item->title }}</a>
                    <p>{{ $item->excerpt ?: 'Краткое описание не задано.' }}</p>
                    <div class="content-row-meta">
                        <span>/news/{{ $item->slug }}</span>
                        <span>Изменена {{ $item->updated_at->format('d.m.Y H:i') }}</span>
                    </div>
                </div>

                <div class="content-row-publication">
                    <span class="publication-state {{ $item->publicationState() }}">{{ $item->publicationLabel() }}</span>
                    <time>{{ $item->published_at?->format('d.m.Y H:i') ?: 'Дата не указана' }}</time>
                </div>

                <div class="content-row-actions">
                    @if ($item->isLive())
                        <a class="button button-secondary" href="{{ route('news.show', $item) }}" target="_blank" rel="noopener">На сайте ↗</a>
                    @endif
                    <a class="button button-primary" href="{{ route('admin.news.edit', $item) }}">Редактировать</a>
                </div>
            </article>
        @endforeach
    </div>

    @if ($news->hasPages())
        <nav class="simple-pagination" aria-label="Навигация по страницам">
            @if ($news->onFirstPage())
                <span class="button button-secondary disabled">← Назад</span>
            @else
                <a class="button button-secondary" href="{{ $news->previousPageUrl() }}">← Назад</a>
            @endif

            <span>Страница {{ $news->currentPage() }} из {{ $news->lastPage() }}</span>

            @if ($news->hasMorePages())
                <a class="button button-secondary" href="{{ $news->nextPageUrl() }}">Вперёд →</a>
            @else
                <span class="button button-secondary disabled">Вперёд →</span>
            @endif
        </nav>
    @endif
@endif
@endsection
