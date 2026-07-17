<?php

namespace App\Providers;

use App\Services\CmsSettings;
use App\Services\GameWorld\GameStatistics;
use App\Services\Pages\PageNavigation;
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

    public function boot(ThemeManager $themes, PageNavigation $pages): void
    {
        $themes->boot();
        view()->share('activeTheme', $themes->manifest());

        view()->composer('theme::partials.header', function ($view) use ($pages): void {
            $view->with([
                'headerPages' => $pages->header(),
                'statisticsNavigationAvailable' => app(GameStatistics::class)->navigationAvailable(),
            ]);
        });

        view()->composer('theme::partials.footer', function ($view) use ($pages): void {
            $view->with('footerPages', $pages->footer());
        });
    }
}
