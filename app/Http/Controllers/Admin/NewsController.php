<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveNewsRequest;
use App\Models\News;
use App\Services\News\NewsHtmlSanitizer;
use App\Services\News\NewsImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class NewsController extends Controller
{
    public function __construct(
        private readonly NewsHtmlSanitizer $sanitizer,
        private readonly NewsImageStorage $images,
    ) {
    }

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
        $coverPath = null;

        if ($request->hasFile('cover_image')) {
            $coverPath = $this->images->storeCover($request->file('cover_image'));
            $data['image'] = $coverPath;
        }

        try {
            $newsItem = DB::transaction(fn (): News => News::query()->create($data));
        } catch (Throwable $exception) {
            $this->images->deleteCover($coverPath);
            throw $exception;
        }

        Log::notice('CMS news created.', [
            'admin_id' => Auth::guard('admin')->id(),
            'news_id' => $newsItem->id,
            'slug' => $newsItem->slug,
            'is_published' => $newsItem->is_published,
            'has_cover' => $newsItem->image !== null,
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
        $data = $this->prepareData($request);
        $oldCover = $news->image;
        $newCover = null;
        $coverChanged = false;

        if ($request->hasFile('cover_image')) {
            $newCover = $this->images->storeCover($request->file('cover_image'));
            $data['image'] = $newCover;
            $coverChanged = true;
        } elseif ($request->boolean('remove_cover_image')) {
            $data['image'] = null;
            $coverChanged = true;
        }

        try {
            DB::transaction(function () use ($news, $data): void {
                $news->update($data);
            });
        } catch (Throwable $exception) {
            $this->images->deleteCover($newCover);
            throw $exception;
        }

        if ($coverChanged && $oldCover !== $news->image) {
            $this->images->deleteCover($oldCover);
        }

        Log::notice('CMS news updated.', [
            'admin_id' => Auth::guard('admin')->id(),
            'news_id' => $news->id,
            'slug' => $news->slug,
            'is_published' => $news->is_published,
            'has_cover' => $news->image !== null,
            'ip_address' => $request->ip(),
        ]);

        return redirect()
            ->route('admin.news.index')
            ->with('status', "Новость «{$news->title}» сохранена.");
    }

    private function prepareData(SaveNewsRequest $request): array
    {
        $data = $request->validated();
        unset($data['cover_image'], $data['remove_cover_image']);

        $data['title'] = trim($data['title']);
        $data['body'] = $this->sanitizer->sanitize(trim($data['body']));
        $data['excerpt'] = trim((string) ($data['excerpt'] ?? ''));
        $data['is_published'] = $request->boolean('is_published');

        if ($this->sanitizer->plainText($data['body']) === '') {
            throw ValidationException::withMessages([
                'body' => 'Добавьте в новость хотя бы один текстовый абзац.',
            ]);
        }

        if ($data['excerpt'] === '') {
            $data['excerpt'] = Str::limit(
                preg_replace('/\s+/u', ' ', $this->sanitizer->plainText($data['body'])) ?? '',
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
