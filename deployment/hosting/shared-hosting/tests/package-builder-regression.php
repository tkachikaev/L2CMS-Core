<?php

declare(strict_types=1);

define('KAEVCMS_PACKAGE_BUILDER_FUNCTIONS_ONLY', true);
require dirname(__DIR__, 2).'/build-shared-hosting-package.php';

function assertPackageBuilder(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$temp = sys_get_temp_dir().'/kaevcms-package-builder-'.bin2hex(random_bytes(6));
$source = $temp.'/source';
$target = $temp.'/target';

try {
    mkdir($source.'/public', 0775, true);
    mkdir($source.'/tests', 0775, true);
    mkdir($source.'/app', 0775, true);
    mkdir($source.'/storage/app', 0775, true);
    mkdir($source.'/database', 0775, true);
    file_put_contents($source.'/app/keep.php', '<?php');
    file_put_contents($source.'/public/index.php', '<?php');
    file_put_contents($source.'/tests/remove.php', '<?php');
    file_put_contents($source.'/.env', 'SECRET=1');
    file_put_contents($source.'/.env.backup', 'SECRET=2');
    file_put_contents($source.'/.env.example', 'APP_NAME=KaevCMS');
    file_put_contents($source.'/storage/app/installed.lock', 'installed');
    file_put_contents($source.'/database/database.sqlite', 'private database');

    copyPackageTree($source, $target, ['public', 'tests', 'storage', 'database/database.sqlite']);

    assertPackageBuilder(is_file($target.'/app/keep.php'), 'Allowed application files must be copied.');
    assertPackageBuilder(! file_exists($target.'/public'), 'The public directory must be handled separately.');
    assertPackageBuilder(! file_exists($target.'/tests'), 'Tests must not enter the production core package.');
    assertPackageBuilder(! file_exists($target.'/.env'), 'A local .env must never enter a hosting package.');
    assertPackageBuilder(! file_exists($target.'/.env.backup'), 'Environment backups must never enter a hosting package.');
    assertPackageBuilder(is_file($target.'/.env.example'), 'The public environment template must remain available.');
    assertPackageBuilder(! file_exists($target.'/storage'), 'Runtime storage must be excluded before a clean skeleton is created.');
    assertPackageBuilder(! file_exists($target.'/database/database.sqlite'), 'A local SQLite database must never enter a hosting package.');
    assertPackageBuilder(packagePathExcluded('tests/Feature/Test.php', ['tests']), 'Nested excluded paths must be recognized.');
    assertPackageBuilder(! packagePathExcluded('app/Test.php', ['tests']), 'Unrelated application paths must not be excluded.');
    assertPackageBuilder(validateDirectoryName('domain.example.test', 'public-dir') === 'domain.example.test', 'Safe domain directory names must be accepted.');

    createCleanRuntimeSkeleton($target);
    assertPackageBuilder(is_file($target.'/storage/framework/sessions/.gitignore'), 'The clean package must recreate writable runtime directories.');
    assertPackageBuilder(is_file($target.'/bootstrap/cache/.gitignore'), 'The clean package must recreate bootstrap/cache.');

    $relative = absolutePackagePath('dist', '/tmp/example');
    assertPackageBuilder(str_ends_with(str_replace('\\', '/', $relative), '/tmp/example/dist'), 'Relative output paths must resolve against the working directory.');

    assertPackageBuilder(packageRelativePath('/srv/kaevcms', '/srv/kaevcms/dist/package') === 'dist/package', 'Paths inside the project must be converted to relative paths.');
    assertPackageBuilder(packageRelativePath('/srv/kaevcms', '/srv/releases/package') === null, 'Paths outside the project must remain external.');
    assertPackageBuilder(packageOutputAllowed('/srv/kaevcms', '/srv/kaevcms/dist'), 'The canonical dist directory must be accepted.');
    assertPackageBuilder(! packageOutputAllowed('/srv/kaevcms', '/srv/kaevcms/build-output'), 'Arbitrary output directories inside the source tree must be rejected.');
    assertPackageBuilder(packageOutputAllowed('/srv/kaevcms', '/srv/releases'), 'An output directory outside the source tree must be accepted.');

    if (class_exists(ZipArchive::class)) {
        $zipSource = $temp.'/zip-source';
        $zipPath = $temp.'/portable.zip';
        mkdir($zipSource.'/nested/path', 0775, true);
        file_put_contents($zipSource.'/nested/path/file.txt', 'portable');
        createPackageZip($zipSource, $zipPath);

        $zip = new ZipArchive;
        assertPackageBuilder($zip->open($zipPath) === true, 'The generated test ZIP must open.');
        $entryNames = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            assertPackageBuilder(is_string($name), 'Every generated ZIP entry must have a readable name.');
            assertPackageBuilder(! str_contains($name, '\\'), 'Generated ZIP entries must use portable forward slashes.');
            $entryNames[] = $name;
        }
        $zip->close();

        assertPackageBuilder(in_array('nested/path/file.txt', $entryNames, true), 'Nested files must keep their portable path inside ZIP.');
    }

    $windowsWrapper = file_get_contents(dirname(__DIR__, 4).'/deployment/windows/build-shared-hosting-package.ps1');
    assertPackageBuilder(is_string($windowsWrapper), 'The Windows shared-hosting wrapper must be readable.');
    assertPackageBuilder(! str_contains($windowsWrapper, '--no-zip'), 'The Windows wrapper must use the ZIP produced by the PHP builder.');
    assertPackageBuilder(! str_contains($windowsWrapper, 'CreateFromDirectory'), 'The Windows wrapper must not repack paths with platform separators.');
    assertPackageBuilder(str_contains($windowsWrapper, 'ZipFile]::OpenRead'), 'The Windows wrapper must validate the generated ZIP.');

    fwrite(STDOUT, "Shared-hosting package builder regression checks passed.\n");
} finally {
    removePackagePath($temp);
}
