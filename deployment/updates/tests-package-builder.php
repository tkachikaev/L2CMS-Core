<?php

declare(strict_types=1);

$builder = file_get_contents(__DIR__.'/build-package.php');
if (! is_string($builder)) {
    throw new RuntimeException('Unable to read the web update package builder.');
}

foreach ([
    'kaevcms-update.json',
    '\'payload/\'.$logicalTarget',
    '\'public/\'.substr($relative, 7)',
    '\'core/\'.$relative',
    '\'public/uploads/\'',
    "'public/uploads/.htaccess'",
    '\'storage/\'',
    '\'vendor/\'',
    'version_compare($minimum, $maximum',
    '\'bootstrap/kaevcms-public-path.php\'',
    '\'sha256\' => $hash',
    'previous-root',
    'cumulativeDeletions',
    'readDeletionHistory',
    'filterActiveDeletions',
    'New deletions detected',
] as $required) {
    if (! str_contains($builder, $required)) {
        throw new RuntimeException("Web update package builder is missing: {$required}");
    }
}

$deletionHistoryPath = __DIR__.'/deletions.json';
$deletionHistory = json_decode((string) file_get_contents($deletionHistoryPath), true);
if (! is_array($deletionHistory)
    || ($deletionHistory['0.32.1'] ?? null) !== ['core/deployment/windows/apply-0.32.0.ps1']
    || ($deletionHistory['0.32.2'] ?? null) !== ['core/deployment/windows/apply-0.32.1.ps1']
    || ($deletionHistory['0.32.3'] ?? null) !== ['core/deployment/windows/apply-0.32.2.ps1']
    || ($deletionHistory['0.32.4'] ?? null) !== ['core/deployment/windows/apply-0.32.3.ps1']
    || ($deletionHistory['0.32.5'] ?? null) !== ['core/deployment/windows/apply-0.32.4.ps1']
    || ($deletionHistory['0.32.6'] ?? null) !== ['core/deployment/windows/apply-0.32.5.ps1']
    || ($deletionHistory['0.32.7'] ?? null) !== ['core/deployment/windows/apply-0.32.6.ps1']
    || ($deletionHistory['0.32.8'] ?? null) !== ['core/deployment/windows/apply-0.32.7.ps1']
    || ($deletionHistory['0.32.9'] ?? null) !== ['core/deployment/windows/apply-0.32.8.ps1']
    || ($deletionHistory['0.32.10'] ?? null) !== ['core/deployment/windows/apply-0.32.9.ps1']
    || ($deletionHistory['0.32.11'] ?? null) !== ['core/deployment/windows/apply-0.32.10.ps1']
    || ($deletionHistory['0.32.12'] ?? null) !== ['core/deployment/windows/apply-0.32.11.ps1']
    || ($deletionHistory['0.32.13'] ?? null) !== ['core/deployment/windows/apply-0.32.12.ps1']) {
    throw new RuntimeException('Web update deletion history does not include the obsolete apply scripts.');
}

echo "Web update package builder regression checks completed successfully.\n";
