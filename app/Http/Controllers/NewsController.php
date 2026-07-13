<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\NewsTranslation;
use App\Services\Localization\LanguageManager;
use Illuminate\View\View;

final class NewsController
{
    public function index(): View
    {
        return view('theme::news.index', [
            'news' => News::query()
                ->with('translations')
                ->published()
                ->latest('published_at')
                ->paginate(10),
        ]);
    }

    public function show(News $news): View
    {
        abort_unless($news->isLive(), 404);
        $news->loadMissing('translations');

        return view('theme::news.show', compact('news'));
    }

    public function showLocalized(string $locale, string $slug, LanguageManager $languages): View
    {
        abort_unless($languages->isEnabled($locale), 404);
        $locale = $languages->normalizeCode($locale) ?? $languages->default();
        $candidates = array_values(array_unique([
            $locale,
            $languages->fallback(),
            $languages->default(),
            'ru',
        ]));

        $translation = null;
        foreach ($candidates as $candidate) {
            $translation = NewsTranslation::query()
                ->where('locale', $candidate)
                ->where('slug', $slug)
                ->first();

            if ($translation !== null) {
                break;
            }
        }

        abort_if($translation === null, 404);

        $news = $translation->news()->with('translations')->firstOrFail();
        abort_unless($news->isLive(), 404);

        return view('theme::news.show', compact('news'));
    }
}
