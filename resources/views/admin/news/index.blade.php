@extends('admin.layouts.panel')

@section('title', 'Новости')
@section('description', 'Создание, оформление, редактирование и публикация новостей публичного сайта.')

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
        <p>Создайте первую новость, добавьте оформление и опубликуйте её на сайте.</p>
        <a class="button button-primary" href="{{ route('admin.news.create') }}">Создать первую новость</a>
    </div>
@else
    <div class="content-list">
        @foreach ($news as $item)
            <article class="content-row">
                <a class="content-row-preview" href="{{ route('admin.news.edit', $item) }}" aria-label="Редактировать: {{ $item->title }}">
                    @if ($item->coverUrl())
                        <img src="{{ $item->coverUrl() }}" alt="">
                    @else
                        <span>Без изображения</span>
                    @endif
                </a>

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
                    <a class="button button-primary" href="{{ route('admin.news.edit', $item) }}">Редактировать</a>
                    <button
                        class="button button-danger"
                        type="button"
                        data-news-delete-open
                        data-news-delete-title="{{ $item->title }}"
                        data-news-delete-url="{{ route('admin.news.destroy', $item) }}"
                    >Удалить</button>
                </div>
            </article>
        @endforeach
    </div>

    @if ($news->hasPages())
        @php
            $firstPage = max(1, $news->currentPage() - 2);
            $lastPage = min($news->lastPage(), $news->currentPage() + 2);
        @endphp

        <nav class="simple-pagination" aria-label="Навигация по страницам">
            @if ($news->onFirstPage())
                <span class="button button-secondary disabled">← Назад</span>
            @else
                <a class="button button-secondary" href="{{ $news->previousPageUrl() }}" rel="prev">← Назад</a>
            @endif

            <div class="pagination-pages" aria-label="Страницы">
                @foreach ($news->getUrlRange($firstPage, $lastPage) as $page => $url)
                    @if ($page === $news->currentPage())
                        <span class="pagination-page active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pagination-page" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            </div>

            @if ($news->hasMorePages())
                <a class="button button-secondary" href="{{ $news->nextPageUrl() }}" rel="next">Вперёд →</a>
            @else
                <span class="button button-secondary disabled">Вперёд →</span>
            @endif
        </nav>
    @endif


    <dialog class="confirm-dialog" data-news-delete-dialog aria-labelledby="delete-news-title">
        <div class="confirm-dialog-card">
            <div class="confirm-dialog-copy">
                <span class="confirm-dialog-mark" aria-hidden="true">!</span>
                <div>
                    <h2 id="delete-news-title">Удалить новость?</h2>
                    <p>Новость «<strong data-news-delete-title></strong>» будет удалена с сайта вместе с неиспользуемыми изображениями. Отменить это действие нельзя.</p>
                </div>
            </div>

            <div class="confirm-dialog-actions">
                <button class="button button-secondary" type="button" data-news-delete-cancel>Отмена</button>

                <form method="POST" action="" data-news-delete-form>
                    @csrf
                    @method('DELETE')
                    <button class="button button-danger" type="submit">Да, удалить</button>
                </form>
            </div>
        </div>
    </dialog>
@endif
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/news-actions.js') }}" defer></script>
@endpush
