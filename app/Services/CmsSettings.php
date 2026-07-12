<?php

namespace App\Services;

use App\Models\CmsSetting;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class CmsSettings
{
    /** @var array<string, string|null> */
    private array $loaded = [];

    public function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->loaded)) {
            return $this->loaded[$key] ?? $default;
        }

        if (! $this->tableExists()) {
            return $default;
        }

        try {
            $value = CmsSetting::query()->where('key', $key)->value('value');
        } catch (Throwable) {
            return $default;
        }

        $this->loaded[$key] = is_string($value) ? $value : null;

        return $this->loaded[$key] ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        if (! $this->tableExists()) {
            throw new RuntimeException('CMS settings table is not available. Run database migrations first.');
        }

        CmsSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        $this->loaded[$key] = $value;
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('cms_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
