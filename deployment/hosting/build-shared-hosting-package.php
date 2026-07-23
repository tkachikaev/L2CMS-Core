<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This packaging tool may only be run from PHP CLI.\n");
    exit(1);
}

if (defined('KAEVCMS_PACKAGE_BUILDER_FUNCTIONS_ONLY')) {
    return;
}

$projectRoot = dirname(__DIR__, 2);
$options = parsePackageOptions(array_slice($argv, 1));
$version = trim((string) @file_get_contents($projectRoot.'/VERSION'));

if ($version === '' || preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) !== 1) {
    failPackage('VERSION is missing or invalid.');
}
if (! is_file($projectRoot.'/vendor/autoload.php')) {
    failPackage('vendor/autoload.php is missing. Run Composer before building a hosting package.');
}

$coreDirectory = validateDirectoryName($options['core-dir'] ?? 'kaevcms-core', 'core-dir');
$publicDirectory = validateDirectoryName($options['public-dir'] ?? 'public_html', 'public-dir');
if ($coreDirectory === $publicDirectory) {
    failPackage('core-dir and public-dir must be different.');
}

$outputDirectory = isset($options['output'])
    ? absolutePackagePath((string) $options['output'], getcwd() ?: $projectRoot)
    : $projectRoot.'/dist';
$packageDirectory = $outputDirectory.'/KaevCMS-'.$version.'-shared-hosting';
$zipPath = $outputDirectory.'/KaevCMS-'.$version.'-shared-hosting.zip';

if (! packageOutputAllowed($projectRoot, $outputDirectory)) {
    failPackage('An output directory inside the project is only allowed under dist/. Use --output=dist or a directory outside the project.');
}

removePackagePath($packageDirectory);
if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
    failPackage('Unable to create output directory: '.$outputDirectory);
}
if (! mkdir($packageDirectory, 0775, true) && ! is_dir($packageDirectory)) {
    failPackage('Unable to create package directory.');
}

$coreTarget = $packageDirectory.'/'.$coreDirectory;
$publicTarget = $packageDirectory.'/'.$publicDirectory;
mkdir($coreTarget, 0775, true);
mkdir($publicTarget, 0775, true);

$excluded = [
    '.env', '.git', '.github', 'auth.json', 'composer.phar', 'dist', 'node_modules',
    'public', 'storage', 'tests', 'bootstrap/cache', 'database/database.sqlite',
    'phpunit.xml', 'phpstan.neon', 'package.json', 'package-lock.json',
    'playwright.config.mjs', '.phpunit.cache', '.phpunit.result.cache', 'npm-debug.log',
    'deployment/windows', 'deployment/vds',
];
copyPackageTree($projectRoot, $coreTarget, $excluded);
createCleanRuntimeSkeleton($coreTarget);
copyPackageTree($projectRoot.'/public', $publicTarget, []);

copy($projectRoot.'/deployment/hosting/shared-hosting/public/index.php', $publicTarget.'/index.php');
copy($projectRoot.'/deployment/hosting/shared-hosting/public/.htaccess', $publicTarget.'/.htaccess');
ensurePackageDirectory($publicTarget.'/install');
copy($projectRoot.'/deployment/hosting/shared-hosting/public/install/index.php', $publicTarget.'/install/index.php');

$pathTemplatePath = $projectRoot.'/deployment/hosting/shared-hosting/public/kaevcms-path.php.template';
$pathTemplate = @file_get_contents($pathTemplatePath);
if (! is_string($pathTemplate)) {
    failPackage('Shared-hosting core path template is missing.');
}
$pathConfig = str_replace('{{CORE_DIRECTORY}}', $coreDirectory, $pathTemplate);
file_put_contents($publicTarget.'/kaevcms-path.php', $pathConfig, LOCK_EX);

$bootstrapOverride = "<?php\n\ndeclare(strict_types=1);\n\nreturn dirname(__DIR__, 2).'/".addslashes($publicDirectory)."';\n";
file_put_contents($coreTarget.'/bootstrap/kaevcms-public-path.php', $bootstrapOverride, LOCK_EX);

$instructions = <<<TXT
KaevCMS {$version} — shared hosting package

РУССКИЙ
1. Распакуйте ОБЕ папки в одном родительском каталоге:
   - {$coreDirectory}/ — закрытое ядро приложения;
   - {$publicDirectory}/ — публичная папка сайта.
2. Направьте домен только на {$publicDirectory}/.
3. Включите HTTPS и откройте домен — появится /install/.
4. Никогда не направляйте домен на {$coreDirectory}/ и не переносите ядро внутрь публичной папки.

Если хостинг уже создал папку домена с другим именем, пересоберите пакет:
php deployment/hosting/build-shared-hosting-package.php --public-dir=ИМЯ_ПАПКИ_ДОМЕНА

ENGLISH
1. Extract BOTH directories into the same parent directory:
   - {$coreDirectory}/ — private application core;
   - {$publicDirectory}/ — website document root.
2. Point the domain only to {$publicDirectory}/.
3. Enable HTTPS and open the domain — /install/ appears.
4. Never point the domain to {$coreDirectory}/ and never move the core into the public directory.

To use an existing domain-directory name, rebuild with:
php deployment/hosting/build-shared-hosting-package.php --public-dir=YOUR_DOMAIN_DIRECTORY

TXT;
file_put_contents($packageDirectory.'/INSTALL-SHARED-HOSTING.txt', $instructions, LOCK_EX);

if (is_dir($projectRoot.'/vendor/phpunit') || is_dir($projectRoot.'/vendor/larastan')) {
    fwrite(STDOUT, "WARNING: vendor appears to include development dependencies. Prefer composer install --no-dev --optimize-autoloader.\n");
}

if (! isset($options['no-zip'])) {
    if (! class_exists(ZipArchive::class)) {
        fwrite(STDOUT, "ZIP extension is unavailable; package directory was created without an archive.\n");
    } else {
        @unlink($zipPath);
        createPackageZip($packageDirectory, $zipPath);
        file_put_contents($zipPath.'.sha256', hash_file('sha256', $zipPath).'  '.basename($zipPath)."\n", LOCK_EX);
        fwrite(STDOUT, "Archive: {$zipPath}\n");
    }
}

fwrite(STDOUT, "Package directory: {$packageDirectory}\n");
fwrite(STDOUT, "Core directory: {$coreDirectory}\nPublic directory: {$publicDirectory}\n");

/** @return array<string, string|bool> */
function parsePackageOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if ($argument === '--no-zip') {
            $options['no-zip'] = true;

            continue;
        }
        if (preg_match('/^--(output|core-dir|public-dir)=(.+)$/', $argument, $matches) === 1) {
            $options[$matches[1]] = $matches[2];

            continue;
        }
        failPackage('Unknown argument: '.$argument);
    }

    return $options;
}

function validateDirectoryName(string $value, string $option): string
{
    $value = trim($value);
    if ($value === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value) !== 1 || $value === '.' || $value === '..') {
        failPackage('Invalid --'.$option.' value. Use only letters, digits, dots, underscores, and hyphens.');
    }

    return $value;
}

function absolutePackagePath(string $path, string $base): string
{
    if (preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path) === 1) {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    return rtrim(str_replace('\\', '/', $base.'/'.$path), '/');
}

function packageOutputAllowed(string $root, string $output): bool
{
    $relative = packageRelativePath($root, $output);
    if ($relative === null) {
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $normalizedOutput = rtrim(str_replace('\\', '/', $output), '/');

        return $normalizedOutput !== $normalizedRoot;
    }

    return $relative === 'dist' || str_starts_with($relative, 'dist/');
}

function packageRelativePath(string $root, string $path): ?string
{
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
    $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');
    if ($normalizedPath === $normalizedRoot) {
        return null;
    }
    $prefix = $normalizedRoot.'/';
    if (! str_starts_with($normalizedPath, $prefix)) {
        return null;
    }

    return substr($normalizedPath, strlen($prefix));
}

/** @param list<string> $excluded */
function copyPackageTree(string $source, string $destination, array $excluded, string $relative = ''): void
{
    ensurePackageDirectory($destination);
    $iterator = new DirectoryIterator($source);
    foreach ($iterator as $item) {
        if ($item->isDot()) {
            continue;
        }
        $itemRelative = ltrim($relative.'/'.$item->getFilename(), '/');
        $normalized = str_replace('\\', '/', $itemRelative);
        if (packagePathExcluded($normalized, $excluded)) {
            continue;
        }
        if ($item->isLink()) {
            failPackage('Symbolic links are not allowed in the hosting package: '.$normalized);
        }
        $target = $destination.'/'.$item->getFilename();
        if ($item->isDir()) {
            copyPackageTree($item->getPathname(), $target, $excluded, $itemRelative);

            continue;
        }
        if (! copy($item->getPathname(), $target)) {
            failPackage('Unable to copy '.$normalized);
        }
    }
}

/** @param list<string> $excluded */
function packagePathExcluded(string $path, array $excluded): bool
{
    if ($path !== '.env.example' && ($path === '.env' || str_starts_with($path, '.env.'))) {
        return true;
    }

    foreach ($excluded as $entry) {
        if ($path === $entry || str_starts_with($path, $entry.'/')) {
            return true;
        }
    }

    return false;
}

function createCleanRuntimeSkeleton(string $coreTarget): void
{
    foreach ([
        'bootstrap/cache',
        'storage/app/private',
        'storage/app/public',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
    ] as $relative) {
        $directory = $coreTarget.'/'.$relative;
        ensurePackageDirectory($directory);
        file_put_contents($directory.'/.gitignore', "*\n!.gitignore\n", LOCK_EX);
    }
}

function ensurePackageDirectory(string $path): void
{
    if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
        failPackage('Unable to create directory: '.$path);
    }
}

function removePackagePath(string $path): void
{
    if (! file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);

        return;
    }
    $items = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
    foreach ($items as $item) {
        removePackagePath($item->getPathname());
    }
    @rmdir($path);
}

function createPackageZip(string $sourceDirectory, string $zipPath): void
{
    $zip = new ZipArchive;
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        failPackage('Unable to create ZIP archive: '.$zipPath);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $item) {
        $relative = portablePackageRelativePath($sourceDirectory, $item->getPathname());
        if ($item->isDir()) {
            $zip->addEmptyDir($relative);
        } else {
            $zip->addFile($item->getPathname(), $relative);
        }
    }
    if (! $zip->close()) {
        failPackage('Unable to finalize ZIP archive.');
    }
}

function portablePackageRelativePath(string $sourceDirectory, string $path): string
{
    $normalizedSource = rtrim(str_replace('\\', '/', $sourceDirectory), '/');
    $normalizedPath = str_replace('\\', '/', $path);
    $prefix = $normalizedSource.'/';

    if (! str_starts_with($normalizedPath, $prefix)) {
        failPackage('ZIP entry is outside the package source directory: '.$path);
    }

    $relative = substr($normalizedPath, strlen($prefix));
    if ($relative === '') {
        failPackage('ZIP entry path is empty: '.$path);
    }

    return $relative;
}

function failPackage(string $message): never
{
    fwrite(STDERR, 'ERROR: '.$message."\n");
    exit(1);
}
