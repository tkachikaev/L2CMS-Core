$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw 'Composer was not found in PATH.'
}

if (-not (Test-Path 'vendor\autoload.php')) {
    throw 'Dependencies are not installed. Run composer install first.'
}

& "$PSScriptRoot\tests\powershell\update-workflow.ps1"

composer validate --strict --no-check-publish
if ($LASTEXITCODE -ne 0) { throw "Composer validation failed with exit code $LASTEXITCODE." }

composer audit --locked --no-interaction
if ($LASTEXITCODE -ne 0) { throw "Composer security audit failed with exit code $LASTEXITCODE." }

composer quality
if ($LASTEXITCODE -ne 0) { throw "Quality checks failed with exit code $LASTEXITCODE." }

php artisan route:clear
if ($LASTEXITCODE -ne 0) { throw "Route cache cleanup failed with exit code $LASTEXITCODE." }

php artisan route:cache
if ($LASTEXITCODE -ne 0) { throw "Route cache build failed with exit code $LASTEXITCODE." }

php artisan route:clear
if ($LASTEXITCODE -ne 0) { throw "Route cache cleanup failed with exit code $LASTEXITCODE." }

Write-Host 'PowerShell updater, Composer audit, Pint, PHPStan, PHPUnit and route cache checks completed successfully.' -ForegroundColor Green
