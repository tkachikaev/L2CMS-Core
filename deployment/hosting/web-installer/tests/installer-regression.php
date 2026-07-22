<?php

declare(strict_types=1);

define('KAEVCMS_INSTALLER_FUNCTIONS_ONLY', true);
require dirname(__DIR__).'/installer.php';

function assertInstaller(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$temp = sys_get_temp_dir().'/kaevcms-installer-'.bin2hex(random_bytes(6));
mkdir($temp, 0700, true);

try {
    $example = $temp.'/.env.example';
    $active = $temp.'/.env';
    file_put_contents($example, "APP_NAME=KaevCMS\nAPP_KEY=\nDB_PASSWORD=\n");
    file_put_contents($active, "APP_NAME=Old\nAPP_KEY=\"base64:KEEP_EXISTING_KEY\"\nDB_PASSWORD=\"old\"\n");

    $specialName = 'My "L2" # Server';
    $specialPassword = "pa# ss\\word\"dollar\$line\nnext";
    $content = buildEnvironmentContent($example, $active, [
        'APP_NAME' => $specialName,
        'DB_PASSWORD' => $specialPassword,
    ]);
    file_put_contents($active, $content);
    $parsed = parseSimpleEnv($active);

    assertInstaller(($parsed['APP_KEY'] ?? null) === 'base64:KEEP_EXISTING_KEY', 'A resumed installation must preserve APP_KEY.');
    assertInstaller(($parsed['APP_NAME'] ?? null) === $specialName, 'Quoted site names must survive .env encoding.');
    assertInstaller(($parsed['DB_PASSWORD'] ?? null) === $specialPassword, 'Database passwords with spaces, #, quotes, slashes, dollars, and newlines must survive .env encoding.');
    assertInstaller(str_starts_with(envEncode('plain'), '"'), 'Environment values must always be quoted.');

    $atomic = $temp.'/atomic.txt';
    file_put_contents($atomic, 'old');
    writeFileAtomically($atomic, 'new', 0600);
    assertInstaller(file_get_contents($atomic) === 'new', 'Atomic writes must safely replace an existing file.');

    $_SERVER['HTTPS'] = 'on';
    $text = installerTranslations('en');
    $secret = 'NeverRenderThisPassword!';
    $html = databaseBody($text, [
        'csrf' => 'token',
        'database' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'kaevcms',
            'username' => 'kaevcms',
            'password' => $secret,
        ],
        'site' => ['url' => 'https://example.test', 'name' => 'KaevCMS'],
    ]);
    assertInstaller(! str_contains($html, $secret), 'The verified database password must never be rendered into HTML.');
    assertInstaller(str_contains($html, 'name="db_password" value=""'), 'The database password input must be blank after verification.');
    assertInstaller(! str_contains($html, 'name="db_password" value="" required'), 'A blank field must be allowed when the server-side session already has the password.');

    $validationFailed = false;
    try {
        validateDatabaseInput([
            'db_host' => '127.0.0.1;unix_socket=/tmp/mysql.sock',
            'db_port' => '3306',
            'db_database' => 'kaevcms',
            'db_username' => 'user',
            'db_password' => 'secret',
        ], $text);
    } catch (InstallerValidationException) {
        $validationFailed = true;
    }
    assertInstaller($validationFailed, 'Unsafe DSN host fragments must be rejected.');

    $lockPath = $temp.'/installing.lock';
    $firstLock = acquireInstallationLock($lockPath, 'first-token');
    $busyDetected = false;
    try {
        acquireInstallationLock($lockPath, 'second-token');
    } catch (InstallerBusyException) {
        $busyDetected = true;
    }
    releaseInstallationLock($firstLock);
    assertInstaller($busyDetected, 'A concurrent installer process must not acquire the same lock.');

    $generic = publicInstallerError(new RuntimeException('raw SQL and /private/path'), $text, 'ABC12345');
    assertInstaller(! str_contains($generic, 'raw SQL'), 'Unexpected internal errors must not be exposed to the browser.');
    assertInstaller(str_contains($generic, 'ABC12345'), 'Generic errors must include a support reference code.');

    echo "Web installer regression checks passed.\n";
} finally {
    $files = glob($temp.'/*') ?: [];
    foreach ($files as $file) {
        @unlink($file);
    }
    @rmdir($temp);
}
