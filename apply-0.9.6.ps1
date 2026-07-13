param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

if (-not (Test-Path 'artisan')) {
    throw 'Run this script from the L2Forge CMS project root.'
}

if (-not (Test-Path '.env')) {
    throw '.env is missing. This patch must be applied to an installed L2Forge CMS project.'
}

if (-not (Test-Path 'VERSION')) {
    throw 'VERSION is missing. Re-extract the complete 0.9.6 patch with file replacement enabled.'
}

$cmsVersion = (Get-Content 'VERSION' -Raw).Trim()

if ($cmsVersion -ne '0.9.6') {
    throw "Unexpected patch version: $cmsVersion"
}

Write-Host "L2Forge CMS $cmsVersion update"
Write-Host 'Fixing project-root resolution in doctor.ps1.'
Write-Host ''

$previousPatchScript = Join-Path $PSScriptRoot 'apply-0.9.5.ps1'
if (Test-Path $previousPatchScript) {
    Remove-Item $previousPatchScript -Force -ErrorAction SilentlyContinue
}

& "$PSScriptRoot\update.ps1" -SkipTests:$SkipTests

Write-Host ''
Write-Host "L2Forge CMS $cmsVersion is ready." -ForegroundColor Green
Write-Host 'Run .\doctor.ps1 from any PowerShell working directory.'
