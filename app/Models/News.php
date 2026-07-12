<?php

namespace App\Models;

use App\Services\News\NewsHtmlSanitizer;
use App\Services\News\NewsImageStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class News extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'image',
        'published_at',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isLive(): bool
    {
        return $this->is_published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function publicationState(): string
    {
        if (! $this->is_published || $this->published_at === null) {
            return 'draft';
        }

        if ($this->published_at?->isFuture()) {
            return 'scheduled';
        }

        return 'published';
    }

    public function publicationLabel(): string
    {
        return match ($this->publicationState()) {
            'published' => 'Опубликована',
            'scheduled' => 'Запланирована',
            default => 'Черновик',
        };
    }

    public function safeBodyHtml(): string
    {
        return app(NewsHtmlSanitizer::class)->sanitize((string) $this->body);
    }

    public function bodyAsPlainText(): string
    {
        return app(NewsHtmlSanitizer::class)->plainText((string) $this->body);
    }

    public function coverUrl(): ?string
    {
        $previewUrl = $this->getAttribute('preview_cover_url');

        if (is_string($previewUrl) && str_starts_with($previewUrl, 'data:image/')) {
            return $previewUrl;
        }

        return app(NewsImageStorage::class)->publicUrl($this->image);
    }
}
