param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function Invoke-Checked {
    param(
        [Parameter(Mandatory = $true)][string]$Label,
        [Parameter(Mandatory = $true)][scriptblock]$Command
    )

    Write-Host "==> $Label"
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Label failed with exit code $LASTEXITCODE."
    }
}

function Write-Utf8NoBom {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Content
    )

    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText((Resolve-Path $Path), $Content, $utf8)
}

function Ensure-Directory {
    param([Parameter(Mandatory = $true)][string]$Path)
    New-Item -Path $Path -ItemType Directory -Force | Out-Null
}

Write-Host 'L2Forge CMS setup'
Write-Host "Project: $PSScriptRoot"

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP was not found in PATH.'
}

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw 'Composer was not found in PATH.'
}

$phpVersionText = (& php -r "echo PHP_VERSION;").Trim()
if ($LASTEXITCODE -ne 0) {
    throw 'Unable to read PHP version.'
}

try {
    $phpVersion = [Version]$phpVersionText
} catch {
    throw "Unable to parse PHP version: $phpVersionText"
}

if ($phpVersion -lt [Version]'8.3.0') {
    throw "PHP 8.3 or newer is required. Installed: $phpVersionText"
}

$requiredExtensions = @(
    'ctype',
    'dom',
    'fileinfo',
    'mbstring',
    'openssl',
    'pdo',
    'pdo_sqlite',
    'tokenizer',
    'xml'
)

$loadedExtensions = & php -r "echo implode(PHP_EOL, get_loaded_extensions());"
if ($LASTEXITCODE -ne 0) {
    throw 'Unable to read loaded PHP extensions.'
}

foreach ($extension in $requiredExtensions) {
    if ($loadedExtensions -notcontains $extension) {
        throw "Required PHP extension is missing: $extension"
    }
}

$directories = @(
    'bootstrap\cache',
    'storage\app\private',
    'storage\app\public',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs',
    'database',
    'public\uploads\news\covers',
    'public\uploads\news\content'
)

foreach ($directory in $directories) {
    Ensure-Directory $directory
}

if (-not (Test-Path '.env')) {
    if (-not (Test-Path '.env.example')) {
        throw '.env.example was not found.'
    }

    Copy-Item '.env.example' '.env'
    Write-Host 'Created .env from .env.example.'
}

$envContent = [System.IO.File]::ReadAllText((Resolve-Path '.env'))
$fixedEnvContent = [regex]::Replace(
    $envContent,
    '(?m)^GAME_SERVER_NAME=([^"\r\n]*\s+[^\r\n]*)$',
    'GAME_SERVER_NAME="$1"'
)
$fixedEnvContent = [regex]::Replace(
    $fixedEnvContent,
    '(?m)^APP_NAME="?L2CMS"?$',
    'APP_NAME="L2Forge CMS"'
)

if ($fixedEnvContent -ne $envContent) {
    Write-Utf8NoBom -Path '.env' -Content $fixedEnvContent
    Write-Host 'Updated legacy values in .env.'
}

$sqlitePath = 'database\database.sqlite'
if (-not (Test-Path $sqlitePath)) {
    New-Item -Path $sqlitePath -ItemType File -Force | Out-Null
    Write-Host 'Created SQLite database file.'
}

Invoke-Checked 'Composer validation' { composer validate --no-check-publish }
Invoke-Checked 'Installing PHP dependencies' { composer install --no-interaction --prefer-dist }

$envAfterInstall = [System.IO.File]::ReadAllText((Resolve-Path '.env'))
$appKeyMatch = [regex]::Match($envAfterInstall, '(?m)^APP_KEY=(.*)$')
if (-not $appKeyMatch.Success -or [string]::IsNullOrWhiteSpace($appKeyMatch.Groups[1].Value)) {
    Invoke-Checked 'Generating application key' { php artisan key:generate --force }
} else {
    Write-Host '==> Application key already exists; keeping it unchanged.'
}

Invoke-Checked 'Clearing Laravel caches' { php artisan optimize:clear }
Invoke-Checked 'Running database migrations and seeders' { php artisan migrate --seed --force }
Invoke-Checked 'Application smoke check' { php artisan about }

if (-not $SkipTests) {
    Invoke-Checked 'Running automated tests' { php artisan test }
}

Write-Host ''
Write-Host 'Setup completed successfully.' -ForegroundColor Green
Write-Host 'Create the first administrator with: php artisan l2forge:admin-create'
Write-Host 'Start the local site with: .\serve.ps1'
Write-Host 'Admin panel: http://127.0.0.1:8000/admin'
