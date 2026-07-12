<?php

namespace App\Models;

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

    public function bodyAsPlainText(): string
    {
        $source = (string) ($this->body ?? '');
        $body = preg_replace('/<br\s*\/?>/i', "\n", $source) ?? $source;
        $body = preg_replace('/<\/p\s*>/i', "\n\n", $body) ?? $body;

        return trim(html_entity_decode(
            strip_tags($body),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));
    }
}
