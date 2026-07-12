param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

if (-not (Test-Path '.env')) {
    throw '.env is missing. Run .\setup.ps1 first.'
}

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP was not found in PATH.'
}

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw 'Composer was not found in PATH.'
}

$directories = @(
    'bootstrap\cache',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs'
)

foreach ($directory in $directories) {
    New-Item -Path $directory -ItemType Directory -Force | Out-Null
}

# Remove stale framework caches before Composer boots the updated application.
if (Test-Path 'vendor\autoload.php') {
    php artisan optimize:clear
    if ($LASTEXITCODE -ne 0) { throw "artisan optimize:clear failed with exit code $LASTEXITCODE." }
}

composer install --no-interaction --prefer-dist
if ($LASTEXITCODE -ne 0) { throw "composer install failed with exit code $LASTEXITCODE." }

php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { throw "artisan optimize:clear failed with exit code $LASTEXITCODE." }

php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw "artisan migrate failed with exit code $LASTEXITCODE." }

if (-not $SkipTests) {
    php artisan test
    if ($LASTEXITCODE -ne 0) { throw "artisan test failed with exit code $LASTEXITCODE." }
}

Write-Host 'Update completed successfully.' -ForegroundColor Green
