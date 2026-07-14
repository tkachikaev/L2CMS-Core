<?php

namespace App\Models;

use App\Services\Localization\LanguageManager;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $rates
 * @property string|null $chronicle
 * @property string|null $mode
 * @property int $sort_order
 * @property int|null $login_server_id
 * @property string|null $driver
 * @property bool $use_login_server_connection
 * @property string|null $database_host
 * @property int|null $database_port
 * @property string|null $database_name
 * @property string|null $database_username
 * @property string|null $database_password
 * @property string|null $database_charset
 * @property-read LoginServer|null $loginServer
 * @property-read Collection<int, GameServerTranslation> $translations
 */
class GameServer extends Model
{
    protected $fillable = [
        'name',
        'rates',
        'chronicle',
        'mode',
        'sort_order',
        'login_server_id',
        'driver',
        'use_login_server_connection',
        'database_host',
        'database_port',
        'database_name',
        'database_username',
        'database_password',
        'database_charset',
    ];

    protected $hidden = [
        'database_password',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'login_server_id' => 'integer',
            'use_login_server_connection' => 'boolean',
            'database_port' => 'integer',
            'database_password' => 'encrypted',
        ];
    }

    /** @return BelongsTo<LoginServer, $this> */
    public function loginServer(): BelongsTo
    {
        return $this->belongsTo(LoginServer::class);
    }

    public function databasePassword(): ?string
    {
        try {
            return is_string($this->database_password) ? $this->database_password : null;
        } catch (DecryptException) {
            return null;
        }
    }

    public function hasDatabasePassword(): bool
    {
        $value = $this->getRawOriginal('database_password');

        return is_string($value) && $value !== '';
    }

    public function connectionConfigured(): bool
    {
        if ($this->driver === null || $this->login_server_id === null) {
            return false;
        }

        if ($this->use_login_server_connection) {
            return true;
        }

        return trim((string) $this->database_host) !== ''
            && $this->database_port !== null
            && trim((string) $this->database_name) !== ''
            && trim((string) $this->database_username) !== ''
            && trim((string) $this->database_charset) !== '';
    }

    /** @return HasMany<GameServerTranslation, $this> */
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
