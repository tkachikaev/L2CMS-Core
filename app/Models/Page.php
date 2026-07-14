<?php

namespace App\Models;

use App\Services\Localization\LanguageManager;
use App\Services\Pages\PageHtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Page extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'is_published',
        'show_in_header',
        'show_in_footer',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_in_header' => 'boolean',
            'show_in_footer' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isLive(): bool
    {
        return (bool) $this->is_published;
    }

    public function publicationLabel(): string
    {
        return $this->isLive() ? __('Published') : __('Draft');
    }

    public function translation(?string $locale = null, bool $withFallback = true): ?PageTranslation
    {
        $locale ??= app()->getLocale();
        $languages = app(LanguageManager::class);
        $locale = $languages->normalizeCode($locale) ?? $languages->default();

        if (! $this->translationsTableExists()) {
            return null;
        }

        $translations = $this->relationLoaded('translations')
            ? $this->getRelation('translations')
            : $this->translations()->whereIn('locale', $this->translationCandidates($locale, $withFallback))->get();

        if (! $translations instanceof Collection) {
            return null;
        }

        foreach ($this->translationCandidates($locale, $withFallback) as $candidate) {
            $translation = $translations->firstWhere('locale', $candidate);
            if ($translation instanceof PageTranslation) {
                return $translation;
            }
        }

        return null;
    }

    public function titleFor(?string $locale = null, bool $withFallback = true): string
    {
        return trim((string) ($this->translation($locale, $withFallback)?->title ?? $this->slug));
    }

    public function slugFor(?string $locale = null, bool $withFallback = true): string
    {
        return trim((string) ($this->translation($locale, $withFallback)?->slug ?? $this->slug));
    }

    public function bodyFor(?string $locale = null, bool $withFallback = true): string
    {
        return (string) ($this->translation($locale, $withFallback)?->body ?? '');
    }

    public function seoTitleFor(?string $locale = null): string
    {
        $translation = $this->translation($locale);

        return trim((string) ($translation?->seo_title ?: $translation?->title ?: $this->slug));
    }

    public function seoDescriptionFor(?string $locale = null): string
    {
        return trim((string) ($this->translation($locale)?->seo_description ?? ''));
    }

    public function hasTranslation(string $locale): bool
    {
        return $this->translation($locale, false) !== null;
    }

    public function safeBodyHtml(?string $locale = null): string
    {
        return app(PageHtmlSanitizer::class)->sanitize($this->bodyFor($locale));
    }

    /** @return array<int, string> */
    private function translationCandidates(string $locale, bool $withFallback): array
    {
        $candidates = [$locale];

        if ($withFallback) {
            $languages = app(LanguageManager::class);
            $candidates[] = $languages->fallback();
            $candidates[] = $languages->default();
            $candidates[] = 'ru';
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function translationsTableExists(): bool
    {
        try {
            return Schema::hasTable('page_translations');
        } catch (Throwable) {
            return false;
        }
    }
}
