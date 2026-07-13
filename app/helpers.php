<?php

use App\Services\GameServerSettings;
use App\Services\MailSettings;
use App\Services\RegistrationSettings;
use App\Services\SiteSettings;
use App\Support\L2Forge;
use App\Support\Themes\ThemeManager;


if (! function_exists('cms_version')) {
    function cms_version(): string
    {
        return L2Forge::version();
    }
}

if (! function_exists('theme_asset')) {
    function theme_asset(string $path): string
    {
        return app(ThemeManager::class)->asset($path);
    }
}

if (! function_exists('site_name')) {
    function site_name(?string $locale = null): string
    {
        return app(SiteSettings::class)->name($locale);
    }
}

if (! function_exists('site_description')) {
    function site_description(?string $locale = null): string
    {
        return app(SiteSettings::class)->description($locale);
    }
}

if (! function_exists('site_logo_url')) {
    function site_logo_url(): ?string
    {
        return app(SiteSettings::class)->logoUrl();
    }
}

if (! function_exists('site_favicon_url')) {
    function site_favicon_url(): ?string
    {
        return app(SiteSettings::class)->faviconUrl();
    }
}

if (! function_exists('site_footer_text')) {
    function site_footer_text(?string $locale = null): string
    {
        return app(SiteSettings::class)->footerText($locale);
    }
}

if (! function_exists('registration_enabled')) {
    function registration_enabled(): bool
    {
        return app(RegistrationSettings::class)->enabled();
    }
}

if (! function_exists('email_verification_required')) {
    function email_verification_required(): bool
    {
        return app(RegistrationSettings::class)->emailVerificationRequired();
    }
}

if (! function_exists('registration_available')) {
    function registration_available(): bool
    {
        $registration = app(RegistrationSettings::class);

        if (! $registration->enabled()) {
            return false;
        }

        return ! $registration->emailVerificationRequired() || app(MailSettings::class)->isReady();
    }
}

if (! function_exists('game_server_settings')) {
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     rates: string,
     *     chronicle: string,
     *     mode: string,
     *     show_rates: bool,
     *     show_chronicle: bool,
     *     show_mode: bool
     * }|null
     */
    function game_server_settings(): ?array
    {
        return app(GameServerSettings::class)->primary();
    }
}

if (! function_exists('game_servers')) {
    /** @return array<int, array<string, int|string|bool>> */
    function game_servers(): array
    {
        return app(GameServerSettings::class)->all();
    }
}

if (! function_exists('language_manager')) {
    function language_manager(): \App\Services\Localization\LanguageManager
    {
        return app(\App\Services\Localization\LanguageManager::class);
    }
}

if (! function_exists('public_route')) {
    /** @param array<string, mixed> $parameters */
    function public_route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $routeLocale = request()->route('locale');
        $locale = is_string($routeLocale) && language_manager()->isEnabled($routeLocale)
            ? $routeLocale
            : null;

        if ($locale !== null && \Illuminate\Support\Facades\Route::has('localized.'.$name)) {
            return route('localized.'.$name, array_merge(['locale' => $locale], $parameters), $absolute);
        }

        return route($name, $parameters, $absolute);
    }
}

if (! function_exists('localized_current_url')) {
    function localized_current_url(string $locale, ?string $requestUri = null): string
    {
        $languages = language_manager();
        $locale = $languages->normalizeCode($locale) ?? $languages->default();
        $requestUri ??= request()->getRequestUri();

        $parts = parse_url($requestUri);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '/') : '/';
        $query = is_array($parts) ? (string) ($parts['query'] ?? '') : '';

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            $path = '/';
            $query = '';
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));
        if ($segments !== [] && $languages->isInstalled($segments[0])) {
            array_shift($segments);
        }

        if ($segments !== [] && in_array(strtolower($segments[0]), ['admin', 'language'], true)) {
            $segments = [];
            $query = '';
        }

        $target = '/'.$locale;
        if ($segments !== []) {
            $target .= '/'.implode('/', array_map('rawurlencode', array_map('rawurldecode', $segments)));
        }

        return url($target).($query !== '' ? '?'.$query : '');
    }
}

if (! function_exists('locale_direction')) {
    function locale_direction(?string $locale = null): string
    {
        return language_manager()->direction($locale);
    }
}

if (! function_exists('news_url')) {
    function news_url(\App\Models\News $news, ?string $locale = null): string
    {
        $routeLocale = request()->route('locale');
        $locale ??= is_string($routeLocale) ? $routeLocale : null;

        if ($locale !== null && language_manager()->isEnabled($locale)) {
            return route('localized.news.show', [
                'locale' => $locale,
                'slug' => $news->slugFor($locale),
            ]);
        }

        return route('news.show', ['news' => $news]);
    }
}
