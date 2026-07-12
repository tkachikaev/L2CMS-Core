<?php

namespace App\Providers;

use App\Services\CmsSettings;
use App\Support\Themes\ThemeManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CmsSettings::class);
        $this->app->singleton(ThemeManager::class, fn ($app) => new ThemeManager(
            themesPath: config('cms.themes_path'),
            fallbackTheme: config('cms.theme'),
            settings: $app->make(CmsSettings::class),
            files: $app->make(Filesystem::class),
        ));
    }

    public function boot(ThemeManager $themes): void
    {
        $themes->boot();
        view()->share('activeTheme', $themes->manifest());
    }
}
