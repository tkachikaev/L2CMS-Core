<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$installer = $projectRoot.'/deployment/hosting/web-installer/installer.php';

if (! is_file($installer)) {
    http_response_code(500);
    echo 'KaevCMS web installer is missing.';
    exit;
}

require $installer;
