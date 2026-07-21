param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$fromVersion = '0.25.1'
$toVersion = '0.25.2'

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
    'account-themes\kaev-aurelia\views\web-inventory\index.blade.php',
    'account-themes\luxury\views\web-inventory\index.blade.php',
    'app\Contracts\GameRewardDeliveryGateway.php',
    'app\Contracts\GameWorldDriver.php',
    'app\Http\Controllers\Account\WebInventoryController.php',
    'app\Jobs\ConfirmRewardDelivery.php',
    'app\Jobs\ProcessRewardDelivery.php',
    'app\Services\GameWorld\MobiusInterludeGameWorldDriver.php',
    'app\Services\Rewards\DriverGameRewardDeliveryGateway.php',
    'app\Services\Rewards\RewardDeliveryProcessor.php',
    'app\Support\Rewards\RewardDeliveryCapabilities.php',
    'app\Support\Rewards\RewardDeliveryResult.php',
    'docs\PRODUCTION.md',
    'docs\ROADMAP.md',
    'docs\WEB_INVENTORY.md',
    'integrations\mobius-interlude\reward-bridge\CharacterSelect.official.sha256',
    'integrations\mobius-interlude\reward-bridge\CharacterSelect.patch',
    'integrations\mobius-interlude\reward-bridge\install.sql',
    'integrations\mobius-interlude\reward-bridge\KaevRewardBridge.java',
    'integrations\mobius-interlude\reward-bridge\KaevRewardDeliveryLock.java',
    'integrations\mobius-interlude\reward-bridge\README.md',
    'lang\en.json',
    'lang\ru.json',
    'tests\Fakes\FakeGameRewardDeliveryGateway.php',
    'tests\Feature\ReleaseMetadataTest.php',
    'tests\Feature\Rewards\MobiusRewardBridgeDriverTest.php',
    'tests\Feature\Rewards\WebInventoryTest.php',
    'tests\powershell\update-workflow.ps1'
)
foreach ($requiredFile in $requiredFiles) {
    if (-not (Test-Path $requiredFile -PathType Leaf)) {
        throw "Patch file is missing: $requiredFile. Re-extract the complete $toVersion patch with file replacement enabled."
    }
}

Write-Host "KaevCMS $fromVersion -> $toVersion update"
Write-Host 'The source release will be verified before migrations or cleanup.'
Write-Host ''

& "$PSScriptRoot\update.ps1" -SkipTests:$SkipTests

Write-Host ''
Write-Host "KaevCMS $toVersion is ready." -ForegroundColor Green
Write-Host 'Offline developer quality gate: .\quality.ps1'
Write-Host 'One-time browser setup: .\browser-setup.ps1'
Write-Host 'Offline browser smoke tests: .\browser-quality.ps1'
Write-Host 'Online dependency audit: .\security-audit.ps1'
