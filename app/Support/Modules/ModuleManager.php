<?php

namespace App\Support\Modules;

use App\Models\ModuleState;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class ModuleManager
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $installedCache = null;

    /** @param list<string> $reservedIds */
    public function __construct(
        private readonly string $modulesPath,
        private readonly array $reservedIds,
        private readonly int $runtimeRetrySeconds,
        private readonly Filesystem $files,
        private readonly ModuleValidator $validator,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function installed(): array
    {
        if ($this->installedCache !== null) {
            return $this->installedCache;
        }

        $states = $this->states();
        $modules = [];
        $seenIds = [];

        if ($this->files->isDirectory($this->modulesPath)) {
            foreach ($this->files->directories($this->modulesPath) as $directory) {
                $id = basename($directory);
                $seenIds[] = $id;
                $modules[] = $this->mergeState($this->validator->inspect($id, $this->modulesPath, $this->reservedIds), $states->get($id));
            }
        }

        foreach ($states as $id => $state) {
            if (in_array($id, $seenIds, true) || ! $state->enabled) {
                continue;
            }

            $modules[] = $this->missingModule($state);
        }

        usort($modules, static function (array $left, array $right): int {
            if ($left['enabled'] !== $right['enabled']) {
                return $left['enabled'] ? -1 : 1;
            }

            if ($left['status'] !== $right['status']) {
                return strcmp((string) $left['status'], (string) $right['status']);
            }

            return strcasecmp((string) $left['name'], (string) $right['name']);
        });

        return $this->installedCache = $modules;
    }

    /** @return array<string, mixed> */
    public function inspect(string $id): array
    {
        foreach ($this->installed() as $module) {
            if ($module['id'] === $id) {
                return $module;
            }
        }

        $state = $this->states()->get($id);
        $module = $this->validator->inspect($id, $this->modulesPath, $this->reservedIds);

        if (
            $state instanceof ModuleState
            && ! $this->files->isDirectory($this->modulesPath.DIRECTORY_SEPARATOR.$id)
        ) {
            return $this->missingModule($state);
        }

        return $this->mergeState($module, $state);
    }

    /** @return array<int, array<string, mixed>> */
    public function enabled(): array
    {
        return array_values(array_filter(
            $this->installed(),
            fn (array $module): bool => $module['status'] === 'enabled'
                || ($module['status'] === 'runtime_error' && $this->runtimeRetryReady($module)),
        ));
    }

    /** @return array<string, mixed> */
    public function enable(string $id): array
    {
        $this->assertStateTable();
        $module = $this->inspect($id);

        if (! $module['valid']) {
            throw new RuntimeException(__('A damaged module cannot be enabled.'));
        }

        if (! $module['compatible']) {
            throw new RuntimeException(__('A module incompatible with this CMS version cannot be enabled.'));
        }

        DB::transaction(static function () use ($module): void {
            ModuleState::query()->updateOrCreate(
                ['id' => $module['id']],
                [
                    'version' => $module['version'],
                    'enabled' => true,
                    'enabled_at' => now(),
                    'disabled_at' => null,
                    'last_error' => null,
                    'last_error_at' => null,
                ],
            );
        });

        $this->refresh();

        return $this->inspect($id);
    }

    /** @return array<string, mixed> */
    public function disable(string $id): array
    {
        $this->assertStateTable();
        $state = ModuleState::query()->find($id);

        if (! $state instanceof ModuleState || ! $state->enabled) {
            throw new RuntimeException(__('The module is not enabled.'));
        }

        $state->forceFill([
            'enabled' => false,
            'disabled_at' => now(),
        ])->save();

        $this->refresh();

        return $this->inspect($id);
    }

    public function refresh(): void
    {
        $this->installedCache = null;
    }

    /** @return Collection<string, ModuleState> */
    private function states(): Collection
    {
        if (! $this->stateTableExists()) {
            return collect();
        }

        try {
            return ModuleState::query()->get()->keyBy('id');
        } catch (Throwable) {
            return collect();
        }
    }

    /** @param array<string, mixed> $module @return array<string, mixed> */
    private function mergeState(array $module, mixed $state): array
    {
        $enabled = $state instanceof ModuleState && $state->enabled;
        $updateAvailable = $state instanceof ModuleState
            && $state->version !== $module['version'];
        $runtimeError = $state instanceof ModuleState ? $state->last_error : null;
        $status = match (true) {
            ! $module['valid'] => 'invalid',
            ! $module['compatible'] => 'incompatible',
            $enabled && $updateAvailable => 'update_pending',
            $enabled && is_string($runtimeError) && $runtimeError !== '' => 'runtime_error',
            $enabled => 'enabled',
            default => 'disabled',
        };

        return array_merge($module, [
            'enabled' => $enabled,
            'status' => $status,
            'stored_version' => $state instanceof ModuleState ? $state->version : null,
            'enabled_at' => $state instanceof ModuleState ? $state->enabled_at : null,
            'disabled_at' => $state instanceof ModuleState ? $state->disabled_at : null,
            'last_error' => $runtimeError,
            'last_error_at' => $state instanceof ModuleState ? $state->last_error_at : null,
            'can_enable' => $module['valid'] && $module['compatible'] && (! $enabled || $updateAvailable),
            'can_disable' => $enabled,
            'update_available' => $updateAvailable,
        ]);
    }

    /** @return array<string, mixed> */
    private function missingModule(ModuleState $state): array
    {
        return [
            'id' => $state->id,
            'name' => $state->id,
            'version' => $state->version,
            'author' => '—',
            'description' => '',
            'schema' => null,
            'cms_min' => null,
            'cms_max' => null,
            'namespace' => null,
            'path' => null,
            'autoload_path' => null,
            'bootstrap_path' => null,
            'views_path' => null,
            'lang_path' => null,
            'route_paths' => [],
            'capabilities' => [],
            'valid' => false,
            'compatible' => false,
            'enabled' => $state->enabled,
            'status' => 'missing',
            'stored_version' => $state->version,
            'enabled_at' => $state->enabled_at,
            'disabled_at' => $state->disabled_at,
            'last_error' => $state->last_error,
            'last_error_at' => $state->last_error_at,
            'can_enable' => false,
            'can_disable' => $state->enabled,
            'update_available' => false,
            'errors' => [__('Module files are missing from the modules directory.')],
            'manifest' => [],
        ];
    }

    /** @param array<string, mixed> $module */
    private function runtimeRetryReady(array $module): bool
    {
        $lastErrorAt = $module['last_error_at'] ?? null;

        return ! $lastErrorAt instanceof CarbonInterface
            || $lastErrorAt->lte(now()->subSeconds($this->runtimeRetrySeconds));
    }

    private function assertStateTable(): void
    {
        if (! $this->stateTableExists()) {
            throw new RuntimeException(__('The module state table is unavailable. Run database migrations first.'));
        }
    }

    private function stateTableExists(): bool
    {
        try {
            return Schema::hasTable('cms_modules');
        } catch (Throwable) {
            return false;
        }
    }
}
