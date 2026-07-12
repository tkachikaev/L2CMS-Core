@extends('theme::layouts.app')
@section('title', 'Новости — '.config('app.name'))
@section('content')
<section class="page-hero">
    <div class="container">
        <p class="eyebrow">ХРОНИКА ПРОЕКТА</p>
        <h1>Новости</h1>
    </div>
</section>
<section class="container page-content">
    <div class="public-news-list">
        @forelse ($news as $item)
            <article class="panel public-news-card">
                <a class="public-news-cover" href="{{ route('news.show', $item) }}" aria-label="{{ $item->title }}">
                    @if ($item->coverUrl())
                        <img src="{{ $item->coverUrl() }}" alt="">
                    @else
                        <span>LINEAGE II</span>
                    @endif
                </a>
                <div class="public-news-copy">
                    <time>{{ $item->published_at?->format('d.m.Y') }}</time>
                    <h2><a href="{{ route('news.show', $item) }}">{{ $item->title }}</a></h2>
                    <p>{{ $item->excerpt }}</p>
                    <a class="public-news-more" href="{{ route('news.show', $item) }}">Читать новость →</a>
                </div>
            </article>
        @empty
            <div class="panel prose"><p>Новостей пока нет.</p></div>
        @endforelse
    </div>

    @if ($news->hasPages())
        <div class="public-pagination">
            @if (! $news->onFirstPage())
                <a class="button button-ghost" href="{{ $news->previousPageUrl() }}">← Назад</a>
            @endif
            <span>Страница {{ $news->currentPage() }} из {{ $news->lastPage() }}</span>
            @if ($news->hasMorePages())
                <a class="button button-ghost" href="{{ $news->nextPageUrl() }}">Вперёд →</a>
            @endif
        </div>
    @endif
</section>
@endsection
