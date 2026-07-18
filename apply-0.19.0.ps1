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
    throw 'VERSION is missing. Re-extract the complete 0.19.0 patch with file replacement enabled.'
}

$cmsVersion = (Get-Content 'VERSION' -Raw).Trim()
if ($cmsVersion -ne '0.19.0') {
    throw "Unexpected patch version: $cmsVersion"
}

$requiredFiles = @(
    'account-themes\luxury\theme.json',
    'account-themes\luxury\views\layouts\app.blade.php',
    'account-themes\luxury\views\dashboard.blade.php',
    'public\account-themes\luxury\assets\css\app.css',
    'public\account-themes\luxury\assets\js\navigation.js',
    'app\Support\Themes\AccountThemeManager.php',
    'app\Http\Controllers\Admin\AccountThemeController.php',
    'resources\views\admin\account-themes\index.blade.php',
    'tests\Feature\Admin\AdminAccountThemeManagementTest.php',
    'tests\browser\specs\player-character-directory.spec.mjs',
    'docs\ACCOUNT_THEMES.md',
    'CHANGELOG.md',
    'README.md',
    'VERSION',
    'update.ps1'
)
foreach ($requiredFile in $requiredFiles) {
    if (-not (Test-Path $requiredFile -PathType Leaf)) {
        throw "Patch file is missing: $requiredFile. Re-extract the complete 0.19.0 patch with file replacement enabled."
    }
}

Write-Host "L2Forge CMS $cmsVersion update"
Write-Host 'Installing the modular luxury player account theme system.'
Write-Host ''

$obsoletePaths = @(
    'resources\views\account',
    'resources\views\livewire\account',
    'resources\views\components\account',
    'public\assets\account'
)
foreach ($obsoletePath in $obsoletePaths) {
    if (Test-Path $obsoletePath) {
        Remove-Item -Path $obsoletePath -Recurse -Force
    }
}

Get-ChildItem -Path $PSScriptRoot -Filter 'apply-*.ps1' -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne 'apply-0.19.0.ps1' } |
    Remove-Item -Force -ErrorAction SilentlyContinue

& "$PSScriptRoot\update.ps1" -SkipTests:$SkipTests

Write-Host ''
Write-Host "L2Forge CMS $cmsVersion is ready." -ForegroundColor Green
Write-Host 'Player account themes: Site -> Player account themes'
Write-Host 'Developer quality gate: .\quality.ps1'
Write-Host 'Browser smoke tests: .\browser-quality.ps1'
