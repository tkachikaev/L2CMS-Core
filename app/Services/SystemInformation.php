<?php

namespace App\Services;

use App\Support\L2Forge;
use App\Support\PasswordHashing;
use Composer\Composer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use PDO;
use Throwable;

final class SystemInformation
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly MailSettings $mailSettings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $database = $this->databaseInformation();
        $extensions = $this->extensionInformation();
        $components = $this->componentInformation($database);

        $information = [
            'cms' => [
                'version' => L2Forge::version(),
            ],
            'software' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'composer' => $this->composerVersion() ?? __('Could not determine'),
                'os' => $this->operatingSystem(),
                'architecture' => (PHP_INT_SIZE * 8).'-bit',
                'sapi' => PHP_SAPI,
            ],
            'environment' => [
                'name' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'php_timezone' => date_default_timezone_get(),
                'cms_timezone' => (string) config('app.timezone'),
                'cache' => (string) config('cache.default'),
                'session' => (string) config('session.driver'),
                'queue' => (string) config('queue.default'),
                'mail' => (string) config('mail.default'),
                'logging' => (string) config('logging.default'),
            ],
            'security' => $this->passwordHashInformation(),
            'database' => $database,
            'components' => $components,
            'extensions' => $extensions,
        ];

        $information['report'] = $this->buildReport($information);

        return $information;
    }

    /**
     * @return array<string, string|bool|null>
     */
    private function databaseInformation(): array
    {
        $connectionName = (string) config('database.default');
        $driver = (string) config("database.connections.{$connectionName}.driver", 'unknown');
        $version = null;
        $connected = false;
        $error = null;

        try {
            $connection = $this->database->connection($connectionName);
            $connection->select('select 1');
            $connected = true;

            try {
                $serverVersion = $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
                $version = is_scalar($serverVersion) ? (string) $serverVersion : null;
            } catch (Throwable) {
                $version = null;
            }
        } catch (Throwable $exception) {
            $error = $exception::class;
        }

        $databasePath = null;
        $databaseSize = null;

        if ($driver === 'sqlite') {
            $configuredPath = (string) config("database.connections.{$connectionName}.database", '');

            if ($configuredPath === ':memory:') {
                $databasePath = ':memory:';
            } elseif ($configuredPath !== '') {
                $databasePath = $this->relativeProjectPath($configuredPath);
                $databaseSize = is_file($configuredPath)
                    ? $this->formatBytes((int) filesize($configuredPath))
                    : __('File not found');
            }
        }

        return [
            'connection' => $connectionName,
            'driver' => $driver,
            'driver_label' => $this->databaseDriverLabel($driver),
            'version' => $version,
            'connected' => $connected,
            'path' => $databasePath,
            'size' => $databaseSize,
            'error' => $error,
        ];
    }

    /**
     * @param  array<string, string|bool|null>  $database
     * @return array<int, array{label: string, state: string, status: string, details: string}>
     */
    private function componentInformation(array $database): array
    {
        $components = [];

        $components[] = [
            'label' => __('Database'),
            'state' => $database['connected'] ? 'success' : 'danger',
            'status' => $database['connected'] ? __('Working') : __('Error'),
            'details' => $database['connected']
                ? trim((string) $database['driver_label'].' '.(string) ($database['version'] ?? ''))
                : __('Could not establish a connection'),
        ];

        foreach ([
            __('Storage directory') => storage_path(),
            __('Bootstrap cache directory') => base_path('bootstrap/cache'),
            __('News image uploads') => public_path('uploads/news'),
            __('Page image uploads') => public_path('uploads/pages'),
            __('Logo and favicon uploads') => public_path('uploads/settings'),
        ] as $label => $path) {
            $writable = $this->canWriteDirectory($path);
            $components[] = [
                'label' => $label,
                'state' => $writable ? 'success' : 'danger',
                'status' => $writable ? __('Writable') : __('Not writable'),
                'details' => $this->relativeProjectPath($path),
            ];
        }

        try {
            $mailConfigured = $this->mailSettings->isConfigured();
            $mailReady = $this->mailSettings->isReady();

            $components[] = [
                'label' => __('Mail system'),
                'state' => $mailReady ? 'success' : ($mailConfigured ? 'warning' : 'neutral'),
                'status' => $mailReady ? __('Configured and verified') : ($mailConfigured ? __('Verification required') : __('Not configured')),
                'details' => $mailReady
                    ? __('A test email was sent successfully')
                    : __('Check the Mail tab'),
            ];
        } catch (Throwable) {
            $components[] = [
                'label' => __('Mail system'),
                'state' => 'warning',
                'status' => __('Status could not be determined'),
                'details' => __('Could not read mail settings'),
            ];
        }

        $queue = (string) config('queue.default');
        $components[] = [
            'label' => __('Queues'),
            'state' => $queue === 'sync' ? 'success' : 'neutral',
            'status' => $queue === 'sync' ? __('Synchronous mode') : __('Driver: :driver', ['driver' => $queue]),
            'details' => $queue === 'sync'
                ? __('A separate queue worker is not required')
                : __('A queue worker must be running for background processing'),
        ];

        $components[] = [
            'label' => __('Laravel scheduler'),
            'state' => 'neutral',
            'status' => __('Not checked automatically'),
            'details' => __('Production requires a system task that runs php artisan schedule:run'),
        ];

        return $components;
    }

    /**
     * @return array<int, array{name: string, loaded: bool, required: bool}>
     */
    private function extensionInformation(): array
    {
        $required = [
            'ctype',
            'dom',
            'fileinfo',
            'mbstring',
            'openssl',
            'pdo',
            'pdo_sqlite',
            'pdo_mysql',
            'tokenizer',
            'xml',
        ];

        $optional = ['curl', 'zip', 'intl', 'gd'];
        $result = [];

        foreach ($required as $extension) {
            $result[] = [
                'name' => $extension,
                'loaded' => extension_loaded($extension),
                'required' => true,
            ];
        }

        foreach ($optional as $extension) {
            $result[] = [
                'name' => $extension,
                'loaded' => extension_loaded($extension),
                'required' => false,
            ];
        }

        return $result;
    }

    private function composerVersion(): ?string
    {
        if (class_exists(Composer::class)) {
            return Composer::VERSION;
        }

        if (! function_exists('shell_exec')) {
            return null;
        }

        $disabledFunctions = array_filter(array_map(
            static fn (string $function): string => trim($function),
            explode(',', (string) ini_get('disable_functions')),
        ));

        if (in_array('shell_exec', $disabledFunctions, true)) {
            return null;
        }

        $output = @shell_exec('composer --version --no-ansi 2>&1');

        if (! is_string($output)) {
            return null;
        }

        if (preg_match('/Composer version\s+([^\s]+)/i', $output, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array{driver: string, label: string, argon2id_supported: bool}
     */
    private function passwordHashInformation(): array
    {
        return [
            'driver' => PasswordHashing::effectiveDriver(),
            'label' => PasswordHashing::label(),
            'argon2id_supported' => PasswordHashing::argon2idSupported(),
        ];
    }

    private function operatingSystem(): string
    {
        $release = trim((string) php_uname('r'));

        return trim(PHP_OS_FAMILY.' '.$release);
    }

    private function databaseDriverLabel(string $driver): string
    {
        return match ($driver) {
            'sqlite' => 'SQLite',
            'mysql' => 'MySQL / MariaDB',
            'pgsql' => 'PostgreSQL',
            'sqlsrv' => 'Microsoft SQL Server',
            default => Str::headline($driver),
        };
    }

    private function canWriteDirectory(string $path): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $testFile = null;
        $written = false;

        try {
            $testFile = rtrim($path, '/\\').DIRECTORY_SEPARATOR.'.l2forge-system-check-'.bin2hex(random_bytes(8));
            $written = file_put_contents($testFile, 'ok', LOCK_EX) !== false && is_file($testFile);
        } catch (Throwable) {
            $written = false;
        } finally {
            if (is_string($testFile) && is_file($testFile)) {
                for ($attempt = 0; $attempt < 3; $attempt++) {
                    if (@unlink($testFile) || ! is_file($testFile)) {
                        break;
                    }

                    usleep(100000);
                    clearstatcache(true, $testFile);
                }
            }
        }

        return $written;
    }

    private function relativeProjectPath(string $path): string
    {
        $base = str_replace('\\', '/', rtrim(base_path(), '/\\'));
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with(Str::lower($normalized), Str::lower($base.'/'))) {
            return ltrim(substr($normalized, strlen($base)), '/');
        }

        return basename($normalized);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return __(':bytes B', ['bytes' => $bytes]);
        }

        $units = [__('KB'), __('MB'), __('GB'), __('TB')];
        $value = $bytes / 1024;
        $unit = $units[0];

        foreach (array_slice($units, 1) as $nextUnit) {
            if ($value < 1024) {
                break;
            }

            $value /= 1024;
            $unit = $nextUnit;
        }

        return number_format($value, $value >= 10 ? 1 : 2, app()->getLocale() === 'ru' ? ',' : '.', app()->getLocale() === 'ru' ? ' ' : ',').' '.$unit;
    }

    /**
     * @param  array<string, mixed>  $information
     */
    private function buildReport(array $information): string
    {
        $database = $information['database'];
        $environment = $information['environment'];
        $software = $information['software'];
        $security = $information['security'];

        return implode(PHP_EOL, [
            __('L2Forge CMS: :value', ['value' => $information['cms']['version']]),
            __('Laravel: :value', ['value' => $software['laravel']]),
            __('PHP: :value', ['value' => $software['php']]),
            __('Composer: :value', ['value' => $software['composer']]),
            __('OS: :value', ['value' => $software['os']]),
            __('Architecture: :value', ['value' => $software['architecture']]),
            __('PHP SAPI: :value', ['value' => $software['sapi']]),
            __('Password hash: :value', ['value' => $security['label']]),
            __('Database: :value', ['value' => $database['driver_label'].($database['version'] ? ' '.$database['version'] : '')]),
            __('Database connection: :value', ['value' => $database['connected'] ? 'OK' : 'ERROR']),
            'APP_ENV: '.$environment['name'],
            'APP_DEBUG: '.($environment['debug'] ? 'true' : 'false'),
            __('CMS timezone: :value', ['value' => $environment['cms_timezone']]),
            __('Cache: :value', ['value' => $environment['cache']]),
            __('Session: :value', ['value' => $environment['session']]),
            __('Queue: :value', ['value' => $environment['queue']]),
            __('Mail: :value', ['value' => $environment['mail']]),
            __('Logging: :value', ['value' => $environment['logging']]),
        ]);
    }
}
