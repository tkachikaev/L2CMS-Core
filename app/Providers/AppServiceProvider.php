<?php

namespace App\Providers;

use App\Services\GameServerSettings;
use App\Services\MailSettings;
use App\Services\News\NewsHtmlSanitizer;
use App\Services\News\NewsImageStorage;
use App\Services\RegistrationSettings;
use App\Services\Settings\SettingsImageStorage;
use App\Services\SiteSettings;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameServerSettings::class);
        $this->app->singleton(MailSettings::class);
        $this->app->singleton(NewsHtmlSanitizer::class);
        $this->app->singleton(NewsImageStorage::class);
        $this->app->singleton(RegistrationSettings::class);
        $this->app->singleton(SettingsImageStorage::class);
        $this->app->singleton(SiteSettings::class);
    }

    public function boot(SiteSettings $siteSettings, MailSettings $mailSettings): void
    {
        $siteSettings->applyConfiguredTimezone();
        $mailSettings->applyConfiguration();

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }
    }
}
