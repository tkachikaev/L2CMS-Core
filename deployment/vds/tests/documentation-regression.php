<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$guides = [
    'English' => $root.'/docs/en/VDS_UBUNTU.md',
    'Russian' => $root.'/docs/ru/VDS_UBUNTU.md',
];

foreach ($guides as $language => $path) {
    $contents = file_get_contents($path);
    if (! is_string($contents)) {
        throw new RuntimeException("Unable to read the {$language} VDS guide.");
    }

    foreach ([
        'Ubuntu Server 24.04 LTS',
        '/var/www/kaevcms/public',
        'php8.3-fpm',
        'composer install --no-dev --optimize-autoloader --no-interaction',
        'composer check-platform-reqs --no-dev',
        'client_max_body_size 512m',
        'fastcgi_pass unix:/run/php/php8.3-fpm.sock;',
        'location = /index.php',
        'location = /install/index.php',
        'location ^~ /uploads/',
        'php artisan kaevcms:update /tmp/KaevCMS-update.zip',
        'certbot --nginx',
        '/etc/cron.d/kaevcms',
        '/etc/systemd/system/kaevcms-queue.service',
        'chmod 775 /var/www/kaevcms',
        'chmod 755 /var/www/kaevcms',
        'chmod 640 /var/www/kaevcms/.env',
    ] as $required) {
        if (! str_contains($contents, $required)) {
            throw new RuntimeException("{$language} VDS guide is missing: {$required}");
        }
    }

    if (str_contains($contents, 'root /var/www/kaevcms;')) {
        throw new RuntimeException("{$language} VDS guide exposes the private project root through nginx.");
    }
}

$english = (string) file_get_contents($guides['English']);
$russian = (string) file_get_contents($guides['Russian']);

if (! str_contains($english, 'Ubuntu 26.04 LTS ships PHP 8.5')) {
    throw new RuntimeException('English VDS guide does not explain the Ubuntu 26.04 compatibility boundary.');
}

if (! str_contains($russian, 'Ubuntu 26.04 LTS поставляется с PHP 8.5')) {
    throw new RuntimeException('Russian VDS guide does not explain the Ubuntu 26.04 compatibility boundary.');
}

echo "Ubuntu VDS documentation regression checks completed successfully.\n";
