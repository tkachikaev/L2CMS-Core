$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw 'Composer was not found in PATH.'
}

if (-not (Test-Path 'vendor\autoload.php')) {
    throw 'Dependencies are not installed. Run composer install first.'
}

composer validate --strict --no-check-publish
if ($LASTEXITCODE -ne 0) { throw "Composer validation failed with exit code $LASTEXITCODE." }

composer quality
if ($LASTEXITCODE -ne 0) { throw "Quality checks failed with exit code $LASTEXITCODE." }

Write-Host 'Pint, PHPStan and PHPUnit completed successfully.' -ForegroundColor Green
