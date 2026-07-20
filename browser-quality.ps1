#requires -Version 5.1
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
    throw 'Node.js and npm are required for browser tests.'
}

if (-not (Test-Path -LiteralPath (Join-Path $PSScriptRoot 'vendor\autoload.php'))) {
    throw 'Composer dependencies are missing. Run composer install first.'
}

npm ci
if ($LASTEXITCODE -ne 0) { throw "npm ci failed with exit code $LASTEXITCODE." }

npm audit --audit-level=high
if ($LASTEXITCODE -ne 0) { throw "npm security audit failed with exit code $LASTEXITCODE." }

npx playwright install chromium
if ($LASTEXITCODE -ne 0) { throw "Playwright browser installation failed with exit code $LASTEXITCODE." }

npm run test:browser
if ($LASTEXITCODE -ne 0) { throw "Browser tests failed with exit code $LASTEXITCODE." }
