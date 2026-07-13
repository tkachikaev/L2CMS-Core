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
    function site_name(): string
    {
        return app(SiteSettings::class)->name();
    }
}

if (! function_exists('site_description')) {
    function site_description(): string
    {
        return app(SiteSettings::class)->description();
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
    function site_footer_text(): string
    {
        return app(SiteSettings::class)->footerText();
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
