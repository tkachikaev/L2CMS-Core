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
    throw 'VERSION is missing. Re-extract the complete 0.17.0 patch with file replacement enabled.'
}

$cmsVersion = (Get-Content 'VERSION' -Raw).Trim()
if ($cmsVersion -ne '0.17.0') {
    throw "Unexpected patch version: $cmsVersion"
}

$requiredFiles = @(
    'app\Livewire\Account\CharacterDirectory.php',
    'app\Models\UserCharacterPreference.php',
    'app\Services\GameAccounts\AccountCharacterDirectory.php',
    'database\migrations\2026_07_18_000100_create_user_character_preferences_table.php',
    'resources\views\livewire\account\character-directory.blade.php',
    'resources\views\components\account\character-row.blade.php',
    'public\assets\account\css\app.css',
    'CHANGELOG.md',
    'README.md',
    'VERSION',
    'docs\PLAYER_ACCOUNT.md',
    'update.ps1'
)
foreach ($requiredFile in $requiredFiles) {
    if (-not (Test-Path $requiredFile -PathType Leaf)) {
        throw "Patch file is missing: $requiredFile. Re-extract the complete 0.17.0 patch with file replacement enabled."
    }
}

Write-Host "L2Forge CMS $cmsVersion update"
Write-Host 'Adding grouped and flat character directory to the player account.'
Write-Host ''

Get-ChildItem -Path $PSScriptRoot -Filter 'apply-*.ps1' -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne 'apply-0.17.0.ps1' } |
    Remove-Item -Force -ErrorAction SilentlyContinue

& "$PSScriptRoot\update.ps1" -SkipTests:$SkipTests

Write-Host ''
Write-Host "L2Forge CMS $cmsVersion is ready." -ForegroundColor Green
Write-Host 'Developer quality gate: .\quality.ps1'
Write-Host 'Browser smoke tests: .\browser-quality.ps1'
