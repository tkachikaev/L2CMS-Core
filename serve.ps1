param(
    [ValidateRange(1, 65535)][int]$Port = 8000,
    [string]$HostAddress = '127.0.0.1'
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP was not found in PATH.'
}

if (-not (Test-Path 'vendor\autoload.php')) {
    throw 'Dependencies are missing. Run .\setup.ps1 first.'
}

if (-not (Test-Path '.env')) {
    throw '.env is missing. Run .\setup.ps1 first.'
}

$requiredDirectories = @(
    'bootstrap\cache',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs'
)

foreach ($directory in $requiredDirectories) {
    New-Item -Path $directory -ItemType Directory -Force | Out-Null
}

Write-Host "Starting L2Forge CMS at http://${HostAddress}:$Port"
Write-Host 'Keep this window open. Press Ctrl+C to stop the site.'
php artisan serve --host=$HostAddress --port=$Port

if ($LASTEXITCODE -ne 0) {
    throw "Laravel development server stopped with exit code $LASTEXITCODE."
}
