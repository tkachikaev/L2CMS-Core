<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\View\View;

final class NewsController
{
    public function index(): View
    {
        return view('theme::news.index', [
            'news' => News::query()
                ->published()
                ->latest('published_at')
                ->paginate(10),
        ]);
    }

    public function show(News $news): View
    {
        abort_unless($news->isLive(), 404);

        return view('theme::news.show', compact('news'));
    }
}
