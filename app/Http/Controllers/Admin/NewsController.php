<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveNewsRequest;
use App\Models\News;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(): View
    {
        return view('admin.news.index', [
            'news' => News::query()->latest('created_at')->paginate(20),
            'totalCount' => News::query()->count(),
            'publishedCount' => News::query()
                ->where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->count(),
            'scheduledCount' => News::query()
                ->where('is_published', true)
                ->where('published_at', '>', now())
                ->count(),
            'draftCount' => News::query()->where('is_published', false)->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.news.create', [
            'newsItem' => new News([
                'is_published' => false,
                'published_at' => now(),
            ]),
        ]);
    }

    public function store(SaveNewsRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request);
        $data['slug'] = $this->makeUniqueSlug($data['title']);

        $newsItem = News::query()->create($data);

        Log::notice('CMS news created.', [
            'admin_id' => Auth::guard('admin')->id(),
            'news_id' => $newsItem->id,
            'slug' => $newsItem->slug,
            'is_published' => $newsItem->is_published,
            'ip_address' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.news.index')
            ->with('status', "Новость «{$newsItem->title}» создана.");
    }

    public function edit(News $news): View
    {
        return view('admin.news.edit', [
            'newsItem' => $news,
        ]);
    }

    public function update(SaveNewsRequest $request, News $news): RedirectResponse
    {
        $news->update($this->prepareData($request));

        Log::notice('CMS news updated.', [
            'admin_id' => Auth::guard('admin')->id(),
            'news_id' => $news->id,
            'slug' => $news->slug,
            'is_published' => $news->is_published,
            'ip_address' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.news.index')
            ->with('status', "Новость «{$news->title}» сохранена.");
    }

    private function prepareData(SaveNewsRequest $request): array
    {
        $data = $request->validated();
        $data['title'] = trim($data['title']);
        $data['body'] = trim($data['body']);
        $data['excerpt'] = trim((string) ($data['excerpt'] ?? ''));
        $data['is_published'] = $request->boolean('is_published');

        if ($data['excerpt'] === '') {
            $data['excerpt'] = Str::limit(
                preg_replace('/\s+/u', ' ', strip_tags($data['body'])) ?? '',
                300
            );
        }

        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    private function makeUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title);

        if ($baseSlug === '') {
            $baseSlug = 'news';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (News::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
