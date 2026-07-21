param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$fromVersion = '0.29.0'
$toVersion = '0.29.2'

if (-not (Test-Path 'artisan')) {
    throw 'Run this script from the KaevCMS project root.'
}

if (-not (Test-Path '.env')) {
    throw '.env is missing. This patch must be applied to an installed KaevCMS project.'
}

if (-not (Test-Path 'VERSION')) {
    throw "VERSION is missing. Re-extract the complete $toVersion patch with file replacement enabled."
}

$cmsVersion = (Get-Content 'VERSION' -Raw).Trim()
if ($cmsVersion -ne $toVersion) {
    throw "Unexpected patch version: $cmsVersion"
}

$requiredFiles = @(
    'CHANGELOG.md',
    'README.md',
    'VERSION',
    'update.ps1',
    'scripts\release-update-support.ps1',
    'app\Http\Controllers\StatisticsController.php',
    'app\Services\GameWorld\GameStatistics.php',
    'app\Services\GameWorld\MobiusGameSchemaInspector.php',
    'app\Services\GameWorld\MobiusGameWorldDriver.php',
    'app\Services\Rewards\DatabaseGameRewardQueueGateway.php',
    'themes\default\views\statistics\index.blade.php',
    'themes\kaev-aurelia\views\statistics\index.blade.php',
    'tests\Feature\ReleaseMetadataTest.php',
    'tests\Unit\MobiusClassNamesTest.php',
    'tests\powershell\update-workflow.ps1'
)
foreach ($requiredFile in $requiredFiles) {
    if (-not (Test-Path $requiredFile -PathType Leaf)) {
        throw "Patch file is missing: $requiredFile. Re-extract the complete $toVersion patch with file replacement enabled."
    }
}

Write-Host "KaevCMS $fromVersion -> $toVersion update"
Write-Host 'The source release will be verified before cleanup or tests.'
Write-Host ''

& "$PSScriptRoot\update.ps1" -SkipTests:$SkipTests

Write-Host ''
Write-Host "KaevCMS $toVersion is ready." -ForegroundColor Green
Write-Host 'Statistics cooldown, schema inspection, and reward queue typing were corrected.'
Write-Host 'Offline developer quality gate: .\quality.ps1'
Write-Host 'One-time browser setup: .\browser-setup.ps1'
Write-Host 'Offline browser smoke tests: .\browser-quality.ps1'
Write-Host 'Online dependency audit: .\security-audit.ps1'
