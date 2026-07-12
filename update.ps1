param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function Write-Utf8NoBom {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Content
    )

    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText((Resolve-Path $Path), $Content, $utf8)
}

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
    'storage\logs',
    'public\uploads\news\covers',
    'public\uploads\news\content'
)

foreach ($directory in $directories) {
    New-Item -Path $directory -ItemType Directory -Force | Out-Null
}

$envContent = [System.IO.File]::ReadAllText((Resolve-Path '.env'))
$updatedEnvContent = [regex]::Replace(
    $envContent,
    '(?m)^APP_NAME="?L2CMS"?$',
    'APP_NAME="L2Forge CMS"'
)

if ($updatedEnvContent -ne $envContent) {
    Write-Utf8NoBom -Path '.env' -Content $updatedEnvContent
    Write-Host 'Updated APP_NAME to L2Forge CMS.'
}

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

Write-Host 'L2Forge CMS update completed successfully.' -ForegroundColor Green
