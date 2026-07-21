param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$fromVersion = '0.28.0'
$toVersion = '0.29.0'

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
    'account-themes\kaev-aurelia\theme.json',
    'account-themes\kaev-aurelia\views\partials\navigation.blade.php',
    'app\Services\GameServer\MobiusGameServerAdapter.php',
    'app\Services\Servers\ServerMonitor.php',
    'docs\AUDIT_0.29.0.md',
    'docs\README.md',
    'docs\ROADMAP.md',
    'docs\WEB_INVENTORY.md',
    'integrations\reward-queue\README.md',
    'modules\promo-codes\resources\views\account\index.blade.php',
    'public\account-themes\kaev-aurelia\assets\css\app.css',
    'tests\Feature\BundledAureliaThemesTest.php',
    'tests\Feature\Modules\PromoCodesModuleTest.php',
    'tests\Feature\ReleaseMetadataTest.php',
    'tests\browser\specs\player-character-directory.spec.mjs',
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
Write-Host 'Stabilization audit completed; Kaev Aurelia Account surfaces and release cleanup were updated.'
Write-Host 'Offline developer quality gate: .\quality.ps1'
Write-Host 'One-time browser setup: .\browser-setup.ps1'
Write-Host 'Offline browser smoke tests: .\browser-quality.ps1'
Write-Host 'Online dependency audit: .\security-audit.ps1'
