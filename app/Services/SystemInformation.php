<?php

namespace App\Services;

use App\Support\L2Forge;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use PDO;
use Throwable;

final class SystemInformation
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly MailSettings $mailSettings,
    ) {
    }

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
                'composer' => $this->composerVersion() ?? 'Не удалось определить',
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
                    : 'Файл не найден';
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
     * @param array<string, string|bool|null> $database
     * @return array<int, array{label: string, state: string, status: string, details: string}>
     */
    private function componentInformation(array $database): array
    {
        $components = [];

        $components[] = [
            'label' => 'База данных',
            'state' => $database['connected'] ? 'success' : 'danger',
            'status' => $database['connected'] ? 'Работает' : 'Ошибка',
            'details' => $database['connected']
                ? trim((string) $database['driver_label'].' '.(string) ($database['version'] ?? ''))
                : 'Подключение установить не удалось',
        ];

        foreach ([
            'Каталог storage' => storage_path(),
            'Каталог bootstrap/cache' => base_path('bootstrap/cache'),
            'Загрузка изображений новостей' => public_path('uploads/news'),
            'Загрузка логотипа и favicon' => public_path('uploads/settings'),
        ] as $label => $path) {
            $writable = $this->canWriteDirectory($path);
            $components[] = [
                'label' => $label,
                'state' => $writable ? 'success' : 'danger',
                'status' => $writable ? 'Доступен для записи' : 'Недоступен для записи',
                'details' => $this->relativeProjectPath($path),
            ];
        }

        try {
            $mailConfigured = $this->mailSettings->isConfigured();
            $mailReady = $this->mailSettings->isReady();

            $components[] = [
                'label' => 'Почтовая система',
                'state' => $mailReady ? 'success' : ($mailConfigured ? 'warning' : 'neutral'),
                'status' => $mailReady ? 'Настроена и проверена' : ($mailConfigured ? 'Требуется проверка' : 'Не настроена'),
                'details' => $mailReady
                    ? 'Тестовое письмо было успешно отправлено'
                    : 'Проверьте вкладку «Почта»',
            ];
        } catch (Throwable) {
            $components[] = [
                'label' => 'Почтовая система',
                'state' => 'warning',
                'status' => 'Состояние не определено',
                'details' => 'Не удалось прочитать почтовые настройки',
            ];
        }

        $queue = (string) config('queue.default');
        $components[] = [
            'label' => 'Очереди',
            'state' => $queue === 'sync' ? 'success' : 'neutral',
            'status' => $queue === 'sync' ? 'Синхронный режим' : 'Драйвер: '.$queue,
            'details' => $queue === 'sync'
                ? 'Отдельный обработчик очереди не требуется'
                : 'Для фоновой обработки должен быть запущен queue worker',
        ];

        $components[] = [
            'label' => 'Планировщик Laravel',
            'state' => 'neutral',
            'status' => 'Не проверяется автоматически',
            'details' => 'На production требуется системный запуск php artisan schedule:run',
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
        if (class_exists(\Composer\Composer::class)) {
            return \Composer\Composer::VERSION;
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

        try {
            $testFile = rtrim($path, '/\\').DIRECTORY_SEPARATOR.'.l2forge-system-check-'.bin2hex(random_bytes(8));

            if (file_put_contents($testFile, 'ok', LOCK_EX) === false || ! is_file($testFile)) {
                return false;
            }

            if (! unlink($testFile)) {
                return false;
            }

            return true;
        } catch (Throwable) {
            if (is_string($testFile) && is_file($testFile)) {
                @unlink($testFile);
            }

            return false;
        }
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
            return $bytes.' Б';
        }

        $units = ['КБ', 'МБ', 'ГБ', 'ТБ'];
        $value = $bytes / 1024;
        $unit = $units[0];

        foreach (array_slice($units, 1) as $nextUnit) {
            if ($value < 1024) {
                break;
            }

            $value /= 1024;
            $unit = $nextUnit;
        }

        return number_format($value, $value >= 10 ? 1 : 2, ',', ' ').' '.$unit;
    }

    /**
     * @param array<string, mixed> $information
     */
    private function buildReport(array $information): string
    {
        $database = $information['database'];
        $environment = $information['environment'];
        $software = $information['software'];

        return implode(PHP_EOL, [
            'L2Forge CMS: '.$information['cms']['version'],
            'Laravel: '.$software['laravel'],
            'PHP: '.$software['php'],
            'Composer: '.$software['composer'],
            'OS: '.$software['os'],
            'Architecture: '.$software['architecture'],
            'PHP SAPI: '.$software['sapi'],
            'Database: '.$database['driver_label'].($database['version'] ? ' '.$database['version'] : ''),
            'Database connection: '.($database['connected'] ? 'OK' : 'ERROR'),
            'APP_ENV: '.$environment['name'],
            'APP_DEBUG: '.($environment['debug'] ? 'true' : 'false'),
            'CMS timezone: '.$environment['cms_timezone'],
            'Cache: '.$environment['cache'],
            'Session: '.$environment['session'],
            'Queue: '.$environment['queue'],
            'Mail: '.$environment['mail'],
            'Logging: '.$environment['logging'],
        ]);
    }
}
