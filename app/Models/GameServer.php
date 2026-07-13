<?php

namespace App\Models;

use App\Services\Localization\LanguageManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GameServer extends Model
{
    protected $fillable = [
        'name',
        'rates',
        'chronicle',
        'mode',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(GameServerTranslation::class);
    }

    public function nameFor(?string $locale = null, bool $withFallback = true): string
    {
        $locale ??= app()->getLocale();
        $languages = app(LanguageManager::class);
        $locale = $languages->normalizeCode($locale) ?? $languages->default();

        if (! $this->translationsTableExists()) {
            return trim((string) $this->name);
        }

        $candidates = [$locale];
        if ($withFallback) {
            $candidates[] = $languages->fallback();
            $candidates[] = $languages->default();
            $candidates[] = 'ru';
        }
        $candidates = array_values(array_unique($candidates));

        $translations = $this->relationLoaded('translations')
            ? $this->getRelation('translations')
            : $this->translations()->whereIn('locale', $candidates)->get();

        if ($translations instanceof Collection) {
            foreach ($candidates as $candidate) {
                $translation = $translations->firstWhere('locale', $candidate);
                if ($translation instanceof GameServerTranslation && trim((string) $translation->name) !== '') {
                    return trim((string) $translation->name);
                }
            }
        }

        return trim((string) $this->name);
    }

    private function translationsTableExists(): bool
    {
        try {
            return Schema::hasTable('game_server_translations');
        } catch (Throwable) {
            return false;
        }
    }
}
