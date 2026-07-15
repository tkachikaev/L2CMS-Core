<?php

namespace App\Providers;

use App\Services\AdminLoginService;
use App\Services\AdminTwoFactorAuthentication;
use App\Services\AuditLogger;
use App\Services\GameAccountSettings;
use App\Services\GameServerSettings;
use App\Services\Localization\LanguageManager;
use App\Services\Localization\LocalizedContentResolver;
use App\Services\MailSettings;
use App\Services\MailTemplateSettings;
use App\Services\News\NewsHtmlSanitizer;
use App\Services\News\NewsImageStorage;
use App\Services\Pages\PageHtmlSanitizer;
use App\Services\Pages\PageImageStorage;
use App\Services\Pages\PageNavigation;
use App\Services\RegistrationSettings;
use App\Services\SecurityLogMaintenance;
use App\Services\SecuritySettings;
use App\Services\Settings\SettingsImageStorage;
use App\Services\SiteSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminLoginService::class);
        $this->app->singleton(AdminTwoFactorAuthentication::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(GameAccountSettings::class);
        $this->app->singleton(GameServerSettings::class);
        $this->app->singleton(MailSettings::class);
        $this->app->singleton(MailTemplateSettings::class);
        $this->app->singleton(LanguageManager::class);
        $this->app->singleton(LocalizedContentResolver::class);
        $this->app->singleton(NewsHtmlSanitizer::class);
        $this->app->singleton(NewsImageStorage::class);
        $this->app->singleton(PageHtmlSanitizer::class);
        $this->app->singleton(PageImageStorage::class);
        $this->app->singleton(PageNavigation::class);
        $this->app->singleton(RegistrationSettings::class);
        $this->app->singleton(SecurityLogMaintenance::class);
        $this->app->singleton(SecuritySettings::class);
        $this->app->singleton(SettingsImageStorage::class);
        $this->app->singleton(SiteSettings::class);
    }

    public function boot(
        SiteSettings $siteSettings,
        MailSettings $mailSettings,
        LanguageManager $languages,
        SecuritySettings $securitySettings,
    ): void {
        $this->configureRateLimiters($securitySettings);

        $defaultLocale = $languages->default();
        $fallbackLocale = $languages->fallback();
        config()->set('app.locale', $defaultLocale);
        config()->set('app.fallback_locale', $fallbackLocale);
        app()->setLocale($defaultLocale);
        app('translator')->setFallback($fallbackLocale);
        $siteSettings->applyConfiguredTimezone();
        $mailSettings->applyConfiguration();

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }
    }

    private function configureRateLimiters(SecuritySettings $securitySettings): void
    {
        RateLimiter::for('admin-login-ip', static function (Request $request) use ($securitySettings): array {
            $ip = $request->ip() ?? 'unknown';
            $key = 'admin-login-ip:'.hash('sha256', $ip);

            $settings = $securitySettings->values();

            return [
                Limit::perMinute($settings['login_ip_per_minute'])->by($key.':minute'),
                Limit::perHour($settings['login_ip_per_hour'])->by($key.':hour'),
            ];
        });

        RateLimiter::for('admin-two-factor-challenge', static function (Request $request): array {
            $ip = $request->ip() ?? 'unknown';
            $challenge = $request->session()->get('admin_two_factor_challenge');
            $adminId = is_array($challenge) && isset($challenge['admin_id'])
                ? (string) $challenge['admin_id']
                : 'unknown';
            $key = 'admin-two-factor:'.hash('sha256', $adminId.'|'.$ip);
            $perMinute = max(1, (int) config('cms.admin.two_factor_max_attempts_per_minute', 5));
            $perHour = max($perMinute, (int) config('cms.admin.two_factor_max_attempts_per_hour', 20));

            return [
                Limit::perMinute($perMinute)->by($key.':minute'),
                Limit::perHour($perHour)->by($key.':hour'),
            ];
        });

        RateLimiter::for('game-account-create', static function (Request $request): Limit {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

            return Limit::perMinutes(10, 3)->by('game-account-create:'.$userId);
        });

        RateLimiter::for('game-account-password', static function (Request $request): Limit {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

            return Limit::perHour(5)->by('game-account-password:'.$userId);
        });
    }
}
