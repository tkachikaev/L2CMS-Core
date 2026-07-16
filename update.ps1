param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

if (-not (Test-Path 'VERSION')) {
    throw 'VERSION is missing. Re-extract the complete L2Forge CMS release or patch.'
}

$cmsVersion = (Get-Content 'VERSION' -Raw).Trim()
if ($cmsVersion -notmatch '^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$') {
    throw "VERSION contains an invalid release number: $cmsVersion"
}

Write-Host "L2Forge CMS $cmsVersion update"
Write-Host "Project: $PSScriptRoot"
Write-Host ''

function Write-Utf8NoBom {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Content
    )

    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText((Resolve-Path $Path), $Content, $utf8)
}

function Get-EnvValue {
    param(
        [string]$Path,
        [string]$Name,
        [string]$Default = ''
    )

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return $Default
    }

    $escapedName = [regex]::Escape($Name)
    $line = Get-Content -LiteralPath $Path |
        Where-Object { $_ -match "^\s*$escapedName\s*=" } |
        Select-Object -First 1

    if ($null -eq $line) {
        return $Default
    }

    $value = (($line -split '=', 2)[1]).Trim()
    if ($value.Length -ge 2) {
        $first = $value[0]
        $last = $value[$value.Length - 1]
        if (($first -eq [char]34 -and $last -eq [char]34) -or ($first -eq [char]39 -and $last -eq [char]39)) {
            $value = $value.Substring(1, $value.Length - 2)
        }
    }

    return $value
}

function Set-EnvValue {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string]$Value
    )

    $content = [System.IO.File]::ReadAllText((Resolve-Path $Path))
    $pattern = '(?m)^\s*' + [regex]::Escape($Name) + '\s*=.*$'
    $replacement = "$Name=$Value"

    if ([regex]::IsMatch($content, $pattern)) {
        $content = [regex]::Replace($content, $pattern, $replacement)
    } else {
        $content = $content.TrimEnd([char[]]"`r`n") + [Environment]::NewLine + $replacement + [Environment]::NewLine
    }

    Write-Utf8NoBom -Path $Path -Content $content
}

function Get-PasswordAlgorithmName {
    param([string]$Driver)

    switch ($Driver.Trim().ToLowerInvariant()) {
        'bcrypt' { return '2y' }
        'argon' { return 'argon2i' }
        'argon2id' { return 'argon2id' }
        default { return $null }
    }
}

function Test-PasswordHashDriver {
    param([string]$Driver)

    $algorithm = Get-PasswordAlgorithmName -Driver $Driver
    if ([string]::IsNullOrWhiteSpace($algorithm)) {
        return $false
    }

    $output = & php -r "echo in_array('$algorithm', password_algos(), true) ? '1' : '0';"
    if ($LASTEXITCODE -ne 0) {
        return $false
    }

    $result = ([string]$output).Trim()
    return $result -eq '1'
}

function Resolve-PasswordHashDriver {
    param([string]$Driver)

    $requested = $Driver.Trim().ToLowerInvariant()
    if ($requested -eq 'auto') {
        if (Test-PasswordHashDriver -Driver 'argon2id') { return 'argon2id' }
        if (Test-PasswordHashDriver -Driver 'bcrypt') { return 'bcrypt' }

        return $null
    }

    if (Test-PasswordHashDriver -Driver $requested) {
        return $requested
    }

    return $null
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

if (-not (Test-Path 'composer.lock')) {
    throw 'composer.lock is missing. Re-extract the complete L2Forge CMS release or patch. Update will not install unpinned dependency versions.'
}

$directories = @(
    'bootstrap\cache',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs',
    'public\uploads\news\covers',
    'public\uploads\news\content',
    'public\uploads\pages\content',
    'public\uploads\settings\logo',
    'public\uploads\settings\favicon'
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
$updatedEnvContent = [regex]::Replace(
    $updatedEnvContent,
    '(?m)^QUEUE_CONNECTION=database[ \t]*\r?$',
    'QUEUE_CONNECTION=sync'
)

if ($updatedEnvContent -ne $envContent) {
    Write-Utf8NoBom -Path '.env' -Content $updatedEnvContent
    Write-Host 'Updated legacy default values in .env.'
}

$hashDriver = (Get-EnvValue -Path '.env' -Name 'HASH_DRIVER' -Default 'auto').ToLowerInvariant()
$knownHashDrivers = @('auto', 'bcrypt', 'argon', 'argon2id')
if ($knownHashDrivers -notcontains $hashDriver) {
    throw "Unsupported HASH_DRIVER: $hashDriver. Use auto, bcrypt, argon or argon2id."
}

$effectiveHashDriver = Resolve-PasswordHashDriver -Driver $hashDriver
if ([string]::IsNullOrWhiteSpace($effectiveHashDriver)) {
    if ($hashDriver -eq 'argon' -or $hashDriver -eq 'argon2id') {
        throw "HASH_DRIVER=$hashDriver is not supported by this PHP executable. Argon2 support is compiled into PHP or supplied by its sodium implementation; it is not a Composer package. Check: php -r `"print_r(password_algos());`", php --ini, and php -m | findstr /I sodium."
    }

    throw "No supported password hashing algorithm is available in this PHP executable."
}

if ($hashDriver -eq 'auto' -and $effectiveHashDriver -eq 'bcrypt') {
    Write-Host '[WARN] Argon2id is unavailable in this PHP executable; HASH_DRIVER=auto selected bcrypt. Use a PHP build whose password_algos() contains argon2id to use Argon2id.' -ForegroundColor Yellow
}

Write-Host "Password hashing: $effectiveHashDriver (requested: $hashDriver)"

# Composer must rebuild the autoloader before Laravel boots. This is important for
# patches that add application classes while an optimized autoloader already exists.
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

Write-Host "L2Forge CMS $cmsVersion update completed successfully." -ForegroundColor Green
