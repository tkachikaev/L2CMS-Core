<?php

namespace App\Services;

use App\Services\Localization\LanguageManager;
use App\Services\Settings\SettingsImageStorage;

final class SiteSettings
{
    public const KEY_NAME = 'site.name';

    public const KEY_DESCRIPTION = 'site.description';

    public const KEY_LOGO = 'site.logo';

    public const KEY_FAVICON = 'site.favicon';

    public const KEY_TIMEZONE = 'site.timezone';

    public const KEY_ADMIN_EMAIL = 'site.admin_email';

    public const KEY_FOOTER_TEXT = 'site.footer_text';

    public function __construct(
        private readonly CmsSettings $settings,
        private readonly SettingsImageStorage $images,
        private readonly LanguageManager $languages,
    ) {}

    /** @return array{name: string, description: string, logo: string|null, logo_url: string|null, favicon: string|null, favicon_url: string|null, timezone: string, admin_email: string, footer_text: string, locale:string} */
    public function values(?string $locale = null): array
    {
        $defaults = $this->defaults();
        $locale = $this->normalizeLocale($locale);
        $localeDefaults = $this->localizedDefaults($locale);
        $candidates = array_values(array_unique([
            $locale,
            $this->languages->fallback(),
            $this->languages->default(),
            'ru',
        ]));

        $localizedDefaults = [];
        foreach ($candidates as $candidate) {
            $localizedDefaults[$this->localizedKey(self::KEY_NAME, $candidate)] = null;
            $localizedDefaults[$this->localizedKey(self::KEY_DESCRIPTION, $candidate)] = null;
            $localizedDefaults[$this->localizedKey(self::KEY_FOOTER_TEXT, $candidate)] = null;
        }

        $legacyName = $this->settings->get(self::KEY_NAME);
        $legacyDescription = $this->settings->get(self::KEY_DESCRIPTION);
        $legacyFooterText = $this->settings->get(self::KEY_FOOTER_TEXT);

        $values = $this->settings->getMany(array_merge([
            self::KEY_NAME => $defaults['name'],
            self::KEY_DESCRIPTION => $defaults['description'],
            self::KEY_LOGO => null,
            self::KEY_FAVICON => null,
            self::KEY_TIMEZONE => $defaults['timezone'],
            self::KEY_ADMIN_EMAIL => $defaults['admin_email'],
            self::KEY_FOOTER_TEXT => $defaults['footer_text'],
        ], $localizedDefaults));

        $logo = $this->images->normalizePath($values[self::KEY_LOGO] ?? null, 'logo');
        $favicon = $this->images->normalizePath($values[self::KEY_FAVICON] ?? null, 'favicon');
        $timezone = $this->normalizeTimezone(
            (string) ($values[self::KEY_TIMEZONE] ?? $defaults['timezone']),
            $defaults['timezone'],
        );

        return [
            'name' => $this->localizedValue(
                $values,
                self::KEY_NAME,
                $candidates,
                $this->legacyOrLocalizedDefault($legacyName, $localeDefaults['name']),
                true,
            ),
            'description' => $this->localizedValue(
                $values,
                self::KEY_DESCRIPTION,
                $candidates,
                $this->legacyOrLocalizedDefault($legacyDescription, $localeDefaults['description']),
            ),
            'logo' => $logo,
            'logo_url' => $this->images->publicUrl($logo),
            'favicon' => $favicon,
            'favicon_url' => $this->images->publicUrl($favicon),
            'timezone' => $timezone,
            'admin_email' => (string) ($values[self::KEY_ADMIN_EMAIL] ?? $defaults['admin_email']),
            'footer_text' => $this->localizedValue(
                $values,
                self::KEY_FOOTER_TEXT,
                $candidates,
                $this->legacyOrLocalizedDefault($legacyFooterText, $localeDefaults['footer_text']),
            ),
            'locale' => $locale,
        ];
    }

    /** @return array<string, array{name:string,description:string,footer_text:string}> */
    public function translations(): array
    {
        $defaults = $this->defaults();
        $defaultLocale = $this->languages->default();
        $keys = [
            self::KEY_NAME => null,
            self::KEY_DESCRIPTION => null,
            self::KEY_FOOTER_TEXT => null,
        ];

        foreach ($this->languages->enabledCodes() as $locale) {
            foreach ([self::KEY_NAME, self::KEY_DESCRIPTION, self::KEY_FOOTER_TEXT] as $base) {
                $keys[$this->localizedKey($base, $locale)] = null;
            }
        }

        $stored = $this->settings->getMany($keys);
        $translations = [];

        foreach ($this->languages->enabledCodes() as $locale) {
            $isDefault = $locale === $defaultLocale;
            $localeDefaults = $this->localizedDefaults($locale);
            $translations[$locale] = [
                'name' => $this->editableLocalizedValue(
                    $stored[$this->localizedKey(self::KEY_NAME, $locale)] ?? null,
                    $stored[self::KEY_NAME] ?? null,
                    $localeDefaults['name'],
                    $isDefault,
                ),
                'description' => $this->editableLocalizedValue(
                    $stored[$this->localizedKey(self::KEY_DESCRIPTION, $locale)] ?? null,
                    $stored[self::KEY_DESCRIPTION] ?? null,
                    $localeDefaults['description'],
                    $isDefault,
                ),
                'footer_text' => $this->editableLocalizedValue(
                    $stored[$this->localizedKey(self::KEY_FOOTER_TEXT, $locale)] ?? null,
                    $stored[self::KEY_FOOTER_TEXT] ?? null,
                    $localeDefaults['footer_text'],
                    $isDefault,
                ),
            ];
        }

        return $translations;
    }

    /**
     * @param  array{name: string, description: string, logo: string|null, favicon: string|null, timezone: string, admin_email: string, footer_text: string}  $values
     * @param  array<string, array{name?:string,description?:string,footer_text?:string}>  $translations
     */
    public function update(array $values, array $translations = []): void
    {
        $defaultLocale = $this->languages->default();

        if ($translations === []) {
            $translations[$defaultLocale] = [
                'name' => $values['name'],
                'description' => $values['description'],
                'footer_text' => $values['footer_text'],
            ];
        }

        $defaultTranslation = $translations[$defaultLocale] ?? reset($translations) ?: [
            'name' => $values['name'],
            'description' => $values['description'],
            'footer_text' => $values['footer_text'],
        ];

        $settings = [
            self::KEY_NAME => trim((string) ($defaultTranslation['name'] ?? $values['name'])),
            self::KEY_DESCRIPTION => (string) ($defaultTranslation['description'] ?? $values['description']),
            self::KEY_LOGO => $values['logo'],
            self::KEY_FAVICON => $values['favicon'],
            self::KEY_TIMEZONE => $values['timezone'],
            self::KEY_ADMIN_EMAIL => $values['admin_email'],
            self::KEY_FOOTER_TEXT => (string) ($defaultTranslation['footer_text'] ?? $values['footer_text']),
        ];

        foreach ($translations as $locale => $translation) {
            $locale = $this->languages->normalizeCode((string) $locale);
            if ($locale === null || ! $this->languages->isEnabled($locale)) {
                continue;
            }

            $settings[$this->localizedKey(self::KEY_NAME, $locale)] = trim((string) ($translation['name'] ?? ''));
            $settings[$this->localizedKey(self::KEY_DESCRIPTION, $locale)] = (string) ($translation['description'] ?? '');
            $settings[$this->localizedKey(self::KEY_FOOTER_TEXT, $locale)] = (string) ($translation['footer_text'] ?? '');
        }

        $this->settings->setMany($settings);
        $this->applyTimezone($values['timezone']);
    }

    public function name(?string $locale = null): string
    {
        return $this->values($locale)['name'];
    }

    public function description(?string $locale = null): string
    {
        return $this->values($locale)['description'];
    }

    public function logoUrl(): ?string
    {
        return $this->values()['logo_url'];
    }

    public function faviconUrl(): ?string
    {
        return $this->values()['favicon_url'];
    }

    public function footerText(?string $locale = null): string
    {
        return $this->values($locale)['footer_text'];
    }

    public function applyConfiguredTimezone(): void
    {
        $defaults = $this->defaults();
        $timezone = $this->settings->get(self::KEY_TIMEZONE, $defaults['timezone']) ?? $defaults['timezone'];

        $this->applyTimezone($this->normalizeTimezone($timezone, $defaults['timezone']));
    }

    private function applyTimezone(string $timezone): void
    {
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        config()->set('app.timezone', $timezone);
        date_default_timezone_set($timezone);
    }

    /** @return array{name: string, description: string, timezone: string, admin_email: string, footer_text: string} */
    private function defaults(): array
    {
        $applicationName = trim((string) config('app.name', 'L2Forge CMS'));
        $siteName = trim((string) config('cms.site_defaults.name', $applicationName));
        $timezone = (string) config('cms.site_defaults.timezone', config('app.timezone', 'UTC'));

        return [
            'name' => $siteName !== '' ? $siteName : ($applicationName !== '' ? $applicationName : 'L2Forge CMS'),
            'description' => (string) config('cms.site_defaults.description', ''),
            'timezone' => $this->normalizeTimezone($timezone, 'UTC'),
            'admin_email' => (string) config('cms.site_defaults.admin_email', ''),
            'footer_text' => (string) config('cms.site_defaults.footer_text', '© 2026 L2Forge-CMS'),
        ];
    }

    /** @return array{name:string,description:string,footer_text:string} */
    private function localizedDefaults(string $locale): array
    {
        $base = $this->defaults();
        $translations = (array) config('cms.site_defaults.translations', []);
        $localized = is_array($translations[$locale] ?? null) ? $translations[$locale] : [];

        return [
            'name' => trim((string) ($localized['name'] ?? $base['name'])) ?: $base['name'],
            'description' => (string) ($localized['description'] ?? $base['description']),
            'footer_text' => (string) ($localized['footer_text'] ?? $base['footer_text']),
        ];
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = $this->languages->normalizeCode((string) ($locale ?? app()->getLocale()));

        return $locale !== null && $this->languages->isEnabled($locale)
            ? $locale
            : $this->languages->default();
    }

    private function localizedKey(string $base, string $locale): string
    {
        return $base.'.'.$locale;
    }

    /** @param array<string, string|null> $values @param array<int, string> $candidates */
    private function localizedValue(array $values, string $base, array $candidates, string $fallback, bool $required = false): string
    {
        foreach ($candidates as $locale) {
            $value = $values[$this->localizedKey($base, $locale)] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $required ? trim($value) : $value;
            }
        }

        return $required ? $this->nonEmptyString($fallback, $this->defaults()['name']) : $fallback;
    }

    private function legacyOrLocalizedDefault(?string $legacy, string $localizedDefault): string
    {
        return $legacy !== null ? $legacy : $localizedDefault;
    }

    private function editableLocalizedValue(
        ?string $localized,
        ?string $legacy,
        string $localizedDefault,
        bool $isDefault,
    ): string {
        if ($localized !== null) {
            return $localized;
        }

        if ($isDefault) {
            return $legacy ?? $localizedDefault;
        }

        return $legacy === null ? $localizedDefault : '';
    }

    private function normalizeTimezone(string $timezone, string $fallback): string
    {
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return in_array($fallback, timezone_identifiers_list(), true) ? $fallback : 'UTC';
    }

    private function nonEmptyString(?string $value, string $fallback): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }
}
