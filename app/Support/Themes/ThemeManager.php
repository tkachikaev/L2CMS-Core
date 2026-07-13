<?php

namespace App\Support\Themes;

use App\Support\L2Forge;
use App\Services\CmsSettings;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use RuntimeException;
use Throwable;

final class ThemeManager
{
    private const ACTIVE_THEME_SETTING = 'theme.active';

    /** @var array<string, mixed> */
    private array $manifest = [];

    private string $activeTheme;

    public function __construct(
        private readonly string $themesPath,
        private readonly string $fallbackTheme,
        private readonly CmsSettings $settings,
        private readonly Filesystem $files,
    ) {
        $this->activeTheme = $fallbackTheme;
    }

    public function boot(): void
    {
        $requestedTheme = $this->settings->get(self::ACTIVE_THEME_SETTING, $this->fallbackTheme) ?? $this->fallbackTheme;
        $theme = $this->inspect($requestedTheme);

        if (! $theme['valid'] || ! $theme['compatible']) {
            $theme = $this->inspect($this->fallbackTheme);
        }

        if (! $theme['valid'] || ! $theme['compatible']) {
            throw new RuntimeException("Fallback theme [{$this->fallbackTheme}] is missing, invalid, or incompatible.");
        }

        $this->applyResolvedTheme($theme);
    }

    /** @return array<int, array<string, mixed>> */
    public function installed(): array
    {
        if (! $this->files->isDirectory($this->themesPath)) {
            return [];
        }

        $themes = [];

        foreach ($this->files->directories($this->themesPath) as $directory) {
            $themes[] = $this->inspect(basename($directory));
        }

        usort($themes, static function (array $left, array $right): int {
            if ($left['active'] !== $right['active']) {
                return $left['active'] ? -1 : 1;
            }

            return strcasecmp((string) $left['name'], (string) $right['name']);
        });

        return $themes;
    }

    /** @return array<string, mixed> */
    public function inspect(string $slug): array
    {
        $result = [
            'slug' => $slug,
            'name' => $slug,
            'version' => '—',
            'author' => '—',
            'description' => '',
            'cms_min' => null,
            'cms_max' => null,
            'preview_url' => null,
            'valid' => false,
            'compatible' => false,
            'active' => $slug === $this->activeTheme,
            'errors' => [],
            'manifest' => [],
        ];

        if (! preg_match('/\A[a-z0-9][a-z0-9_-]*\z/', $slug)) {
            $result['errors'][] = __('Invalid theme directory name.');

            return $result;
        }

        $root = realpath($this->themesPath);
        $path = realpath($this->themesPath.DIRECTORY_SEPARATOR.$slug);

        if ($root === false || $path === false || ! str_starts_with($path.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR)) {
            $result['errors'][] = __('Theme directory not found.');

            return $result;
        }

        $manifestPath = $path.DIRECTORY_SEPARATOR.'theme.json';

        if (! $this->files->isFile($manifestPath)) {
            $result['errors'][] = __('The theme.json file was not found.');

            return $result;
        }

        try {
            $manifest = json_decode($this->files->get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $result['errors'][] = __('The theme.json file contains invalid JSON.');

            return $result;
        }

        if (! is_array($manifest)) {
            $result['errors'][] = __('The theme.json file must contain a JSON object.');

            return $result;
        }

        $result['manifest'] = $manifest;
        $result['name'] = (string) Arr::get($manifest, 'name', $slug);
        $result['version'] = (string) Arr::get($manifest, 'version', '—');
        $result['author'] = (string) Arr::get($manifest, 'author', '—');
        $result['description'] = (string) Arr::get($manifest, 'description', '');
        $result['cms_min'] = $this->nullableString(Arr::get($manifest, 'cms_min'));
        $result['cms_max'] = $this->nullableString(Arr::get($manifest, 'cms_max'));

        foreach (['name', 'slug', 'version', 'author'] as $requiredField) {
            if (! is_string(Arr::get($manifest, $requiredField)) || trim((string) Arr::get($manifest, $requiredField)) === '') {
                $result['errors'][] = __('The :field field is missing from theme.json.', ['field' => $requiredField]);
            }
        }

        if (Arr::get($manifest, 'slug') !== $slug) {
            $result['errors'][] = __('The slug field does not match the theme directory name.');
        }

        foreach (['views/layouts/app.blade.php', 'views/home.blade.php'] as $requiredFile) {
            if (! $this->files->isFile($path.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $requiredFile))) {
                $result['errors'][] = __('Required file :file was not found.', ['file' => $requiredFile]);
            }
        }

        $result['valid'] = $result['errors'] === [];
        $result['compatible'] = $result['valid'] && $this->isCompatible($result['cms_min'], $result['cms_max']);
        $result['active'] = $slug === $this->activeTheme;
        $result['preview_url'] = $this->previewUrl($slug, $manifest);

        if ($result['valid'] && ! $result['compatible']) {
            $result['errors'][] = __('The theme is incompatible with the current CMS version.');
        }

        return $result;
    }

    public function activate(string $slug): void
    {
        $theme = $this->inspect($slug);

        if (! $theme['valid']) {
            throw new RuntimeException(__('A damaged theme cannot be activated.'));
        }

        if (! $theme['compatible']) {
            throw new RuntimeException(__('A theme incompatible with this CMS version cannot be activated.'));
        }

        $this->settings->set(self::ACTIVE_THEME_SETTING, $slug);
        $this->applyResolvedTheme($theme);
    }

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        return $this->manifest;
    }

    public function name(): string
    {
        return $this->activeTheme;
    }

    public function themePath(): string
    {
        return rtrim($this->themesPath, '/\\').DIRECTORY_SEPARATOR.$this->activeTheme;
    }

    public function asset(string $path): string
    {
        return asset('themes/'.$this->activeTheme.'/assets/'.ltrim($path, '/'));
    }

    /** @param array<string, mixed> $theme */
    private function applyResolvedTheme(array $theme): void
    {
        $this->activeTheme = (string) $theme['slug'];
        $this->manifest = (array) $theme['manifest'];

        $viewPaths = [$this->themePath().DIRECTORY_SEPARATOR.'views'];
        $fallbackViews = rtrim($this->themesPath, '/\\')
            .DIRECTORY_SEPARATOR.$this->fallbackTheme
            .DIRECTORY_SEPARATOR.'views';

        if ($this->activeTheme !== $this->fallbackTheme && $this->files->isDirectory($fallbackViews)) {
            $viewPaths[] = $fallbackViews;
        }

        view()->replaceNamespace('theme', $viewPaths);
        view()->share('activeTheme', $this->manifest);
    }

    private function isCompatible(?string $minimum, ?string $maximum): bool
    {
        $cmsVersion = L2Forge::version();

        if ($minimum !== null && version_compare($cmsVersion, $minimum, '<')) {
            return false;
        }

        if ($maximum !== null && version_compare($cmsVersion, $maximum, '>')) {
            return false;
        }

        return true;
    }

    /** @param array<string, mixed> $manifest */
    private function previewUrl(string $slug, array $manifest): ?string
    {
        $preview = Arr::get($manifest, 'preview');

        if (! is_string($preview) || ! preg_match('/\A[a-zA-Z0-9_\/.\-]+\z/', $preview)) {
            return null;
        }

        $publicThemeRoot = public_path('themes/'.$slug);
        $publicThemePath = realpath($publicThemeRoot);
        $previewPath = realpath($publicThemeRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $preview));

        if ($publicThemePath === false || $previewPath === false || ! str_starts_with($previewPath, $publicThemePath.DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (! $this->files->isFile($previewPath)) {
            return null;
        }

        return asset('themes/'.$slug.'/'.ltrim($preview, '/'));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
