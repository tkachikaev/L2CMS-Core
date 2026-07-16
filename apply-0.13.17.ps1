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
    throw 'VERSION is missing. Re-extract the complete 0.13.17 patch with file replacement enabled.'
}

$cmsVersion = (Get-Content 'VERSION' -Raw).Trim()
if ($cmsVersion -ne '0.13.17') {
    throw "Unexpected patch version: $cmsVersion"
}

$requiredFiles = @(
    'resources\views\livewire\admin\login-server-manager.blade.php',
    'resources\views\livewire\admin\game-server-manager.blade.php',
    'public\assets\admin\css\app.css',
    'public\assets\account\css\app.css',
    'update.ps1'
)

foreach ($requiredFile in $requiredFiles) {
    if (-not (Test-Path $requiredFile -PathType Leaf)) {
        throw "Patch file is missing: $requiredFile. Re-extract the complete 0.13.17 patch with file replacement enabled."
    }
}

Write-Host "L2Forge CMS $cmsVersion update"
Write-Host 'Applying server drawer, connection badge, avatar and inline validation fixes.'
Write-Host ''

Get-ChildItem -Path $PSScriptRoot -Filter 'apply-*.ps1' -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne 'apply-0.13.17.ps1' } |
    Remove-Item -Force -ErrorAction SilentlyContinue

& "$PSScriptRoot\update.ps1" -SkipTests:$SkipTests

Write-Host ''
Write-Host "L2Forge CMS $cmsVersion is ready." -ForegroundColor Green
Write-Host 'Developer quality gate: .\quality.ps1'
