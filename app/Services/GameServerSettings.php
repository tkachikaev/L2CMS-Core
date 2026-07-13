<?php

namespace App\Services;

use App\Models\GameServer;
use App\Services\Localization\LanguageManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class GameServerSettings
{
    public function __construct(private readonly LanguageManager $languages)
    {
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     rates: string,
     *     chronicle: string,
     *     mode: string,
     *     show_rates: bool,
     *     show_chronicle: bool,
     *     show_mode: bool,
     *     translations: array<string,string>
     * }>
     */
    public function all(?string $locale = null): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        try {
            return GameServer::query()
                ->with('translations')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (GameServer $server): array => $this->normalize($server, $locale))
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function primary(?string $locale = null): ?array
    {
        return $this->all($locale)[0] ?? null;
    }

    /** @param array{name: string, rates?: string|null, chronicle?: string|null, mode?: string|null, translations?:array<string,string>} $values */
    public function create(array $values): GameServer
    {
        $this->ensureTableExists();

        return DB::transaction(function () use ($values): GameServer {
            $nextSortOrder = ((int) GameServer::query()->max('sort_order')) + 1;
            $defaultName = $this->defaultName($values);

            $server = GameServer::query()->create([
                'name' => $defaultName,
                'rates' => $this->nullableString($values['rates'] ?? null),
                'chronicle' => $this->nullableString($values['chronicle'] ?? null),
                'mode' => $this->nullableString($values['mode'] ?? null),
                'sort_order' => $nextSortOrder,
            ]);

            $this->saveTranslations($server, (array) ($values['translations'] ?? []), $defaultName);

            return $server;
        });
    }

    /** @param array{name: string, rates?: string|null, chronicle?: string|null, mode?: string|null, translations?:array<string,string>} $values */
    public function update(GameServer $server, array $values): void
    {
        DB::transaction(function () use ($server, $values): void {
            $defaultName = $this->defaultName($values);

            $server->update([
                'name' => $defaultName,
                'rates' => $this->nullableString($values['rates'] ?? null),
                'chronicle' => $this->nullableString($values['chronicle'] ?? null),
                'mode' => $this->nullableString($values['mode'] ?? null),
            ]);

            $this->saveTranslations($server, (array) ($values['translations'] ?? []), $defaultName);
        });
    }

    public function delete(GameServer $server): void
    {
        $server->delete();
    }

    private function normalize(GameServer $server, ?string $locale): array
    {
        $rates = trim((string) $server->rates);
        $chronicle = trim((string) $server->chronicle);
        $mode = trim((string) $server->mode);
        $translations = [];

        foreach ($this->languages->enabledCodes() as $code) {
            $translations[$code] = $server->nameFor($code, false);
            if ($translations[$code] === trim((string) $server->name) && $code !== $this->languages->default()) {
                $own = $server->translations->firstWhere('locale', $code);
                $translations[$code] = $own ? trim((string) $own->name) : '';
            }
        }

        return [
            'id' => (int) $server->id,
            'name' => $server->nameFor($locale),
            'rates' => $rates,
            'chronicle' => $chronicle,
            'mode' => $mode,
            'show_rates' => $rates !== '',
            'show_chronicle' => $chronicle !== '',
            'show_mode' => $this->modeIsVisible($mode),
            'translations' => $translations,
        ];
    }

    /** @param array<string,mixed> $values */
    private function defaultName(array $values): string
    {
        $translations = (array) ($values['translations'] ?? []);
        $default = trim((string) ($translations[$this->languages->default()] ?? $values['name'] ?? ''));

        if ($default === '') {
            $default = trim((string) ($values['name'] ?? ''));
        }

        return $default;
    }

    /** @param array<string,mixed> $translations */
    private function saveTranslations(GameServer $server, array $translations, string $defaultName): void
    {
        if (! Schema::hasTable('game_server_translations')) {
            return;
        }

        if ($translations === []) {
            $translations[$this->languages->default()] = $defaultName;
        }

        foreach ($this->languages->enabledCodes() as $locale) {
            $name = trim((string) ($translations[$locale] ?? ''));

            if ($name === '') {
                $server->translations()->where('locale', $locale)->delete();
                continue;
            }

            $server->translations()->updateOrCreate(
                ['locale' => $locale],
                ['name' => $name],
            );
        }
    }

    private function modeIsVisible(string $mode): bool
    {
        return $mode !== '' && mb_strtolower($mode) !== 'none';
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function ensureTableExists(): void
    {
        if (! $this->tableExists()) {
            throw new RuntimeException('Game servers table is not available. Run database migrations first.');
        }
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('game_servers');
        } catch (Throwable) {
            return false;
        }
    }
}
