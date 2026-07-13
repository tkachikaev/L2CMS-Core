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
    throw 'VERSION is missing. Re-extract the complete 0.10.0 patch with file replacement enabled.'
}

$cmsVersion = (Get-Content 'VERSION' -Raw).Trim()
if ($cmsVersion -ne '0.10.0') {
    throw "Unexpected patch version: $cmsVersion"
}

Write-Host "L2Forge CMS $cmsVersion update"
Write-Host 'Adding Russian and English localization, translated content and language-pack support.'
Write-Host ''

Get-ChildItem -LiteralPath $PSScriptRoot -Filter 'apply-*.ps1' -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne 'apply-0.10.0.ps1' } |
    Remove-Item -Force -ErrorAction SilentlyContinue

& "$PSScriptRoot\update.ps1" -SkipTests:$SkipTests

Write-Host ''
Write-Host "L2Forge CMS $cmsVersion is ready." -ForegroundColor Green
Write-Host 'Open Settings -> Languages to verify the enabled languages.'
