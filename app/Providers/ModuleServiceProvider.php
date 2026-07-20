<?php

namespace App\Providers;

use App\Support\Modules\ModuleManager;
use App\Support\Modules\ModuleRuntime;
use App\Support\Modules\ModuleValidator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleValidator::class);
        $this->app->singleton(ModuleManager::class, fn (Application $app): ModuleManager => new ModuleManager(
            modulesPath: (string) config('cms.modules_path'),
            reservedIds: (array) config('cms.modules.reserved_ids', []),
            runtimeRetrySeconds: max(1, (int) config('cms.modules.runtime_retry_seconds', 60)),
            files: $app->make(Filesystem::class),
            validator: $app->make(ModuleValidator::class),
        ));
        $this->app->singleton(ModuleRuntime::class);
    }

    public function boot(ModuleManager $modules, ModuleRuntime $runtime): void
    {
        // Load module resources after Laravel has loaded the core route files or
        // the core route cache. This keeps module routes dynamic and removable.
        $this->app->booted(static function () use ($modules, $runtime): void {
            $runtime->bootEnabled($modules);
        });
    }
}
