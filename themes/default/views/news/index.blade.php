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
    <div class="panel article-list">
        @forelse ($news as $item)
            <article>
                <time>{{ $item->published_at?->format('d.m.Y') }}</time>
                <h2><a href="{{ route('news.show', $item) }}">{{ $item->title }}</a></h2>
                <p>{{ $item->excerpt }}</p>
            </article>
        @empty
            <p>Новостей пока нет.</p>
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
