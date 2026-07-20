param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$fromVersion = '0.23.8'
$toVersion = '0.23.9'

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
    'phpunit.xml',
    'scripts\release-update-support.ps1',
    'app\Console\Commands\ReleaseVersionCommand.php',
    'app\Console\Commands\MaintenanceStatusCommand.php',
    'app\Services\Releases\InstalledVersion.php',
    'app\Jobs\Mail\SendUserMailNotification.php',
    'app\Console\Commands\DrainDatabaseQueueCommand.php',
    'app\Console\Commands\CleanupQueueDataCommand.php',
    'app\Auth\AdminAccessPolicy.php',
    'app\Http\Controllers\Admin\MailSettingsController.php',
    'app\Models\FailedJob.php',
    'app\Providers\AppServiceProvider.php',
    'app\Services\MailSettings.php',
    'app\Services\SystemInformation.php',
    'config\cms.php',
    'routes\admin.php',
    'routes\console.php',
    'lang\ru.json',
    'lang\en.json',
    'public\assets\admin\css\app.css',
    'resources\views\admin\dashboard.blade.php',
    'resources\views\admin\settings\_system_tabs.blade.php',
    'resources\views\admin\settings\system.blade.php',
    'app\Http\Controllers\Admin\QueueOperationsController.php',
    'app\Services\Infrastructure\QueueMaintenance.php',
    'app\Services\Infrastructure\RuntimeDiagnostics.php',
    'resources\views\admin\settings\queue.blade.php',
    'public\assets\admin\js\queue.js',
    'tests\Feature\Admin\QueueInfrastructureTest.php',
    'tests\Feature\ReleaseInstalledVersionTest.php',
    'tests\Feature\ReleaseMetadataTest.php',
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
Write-Host 'Developer quality gate: .\quality.ps1'
Write-Host 'Browser smoke tests: .\browser-quality.ps1'
