param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
Set-Location -LiteralPath $ProjectRoot

$fromVersion = '0.32.13'
$toVersion = '0.32.14'

if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot 'artisan') -PathType Leaf)) {
    throw 'The KaevCMS project root could not be found.'
}

if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot '.env') -PathType Leaf)) {
    throw '.env is missing. This patch is for an installed KaevCMS project. For a new hosting installation, open /install/.'
}

$versionPath = Join-Path $ProjectRoot 'VERSION'
if (-not (Test-Path -LiteralPath $versionPath -PathType Leaf)) {
    throw "VERSION is missing. Re-extract the complete $toVersion patch with file replacement enabled."
}

$cmsVersion = (Get-Content -LiteralPath $versionPath -Raw).Trim()
if ($cmsVersion -ne $toVersion) {
    throw "Unexpected patch version: $cmsVersion"
}

$requiredFiles = @(
    'CHANGELOG.md'
    'README.md'
    'VERSION'
    'app\Console\Commands\InstallSystemUpdateCommand.php'
    'public\uploads\.htaccess'
    'public\index.php'
    'public\.htaccess'
    'public\install\index.php'
    'bootstrap\app.php'
    'bootstrap\cache\.gitignore'
    'deployment\hosting\README.md'
    'deployment\vds\README.md'
    'deployment\vds\tests\documentation-regression.php'
    'deployment\hosting\build-shared-hosting-package.php'
    'deployment\hosting\archive-shared-hosting-package.php'
    'deployment\hosting\web-installer\installer.php'
    'deployment\hosting\web-installer\tests\installer-regression.php'
    'deployment\hosting\shared-hosting\README.md'
    'deployment\hosting\shared-hosting\public\index.php'
    'deployment\hosting\shared-hosting\public\install\index.php'
    'deployment\hosting\shared-hosting\public\kaevcms-path.php.template'
    'deployment\hosting\shared-hosting\tests\layout-regression.php'
    'deployment\hosting\shared-hosting\tests\package-builder-regression.php'
    'docs\README.md'
    'docs\en\INSTALLATION.md'
    'docs\en\VDS_UBUNTU.md'
    'docs\en\SHARED_HOSTING.md'
    'docs\en\SECURITY.md'
    'docs\ru\INSTALLATION.md'
    'docs\ru\VDS_UBUNTU.md'
    'docs\ru\SHARED_HOSTING.md'
    'docs\ru\SECURITY.md'
    'docs\WEB_INSTALLER.md'
    'docs\WEB_UPDATER.md'
    'routes\admin.php'
    'config\cms.php'
    'app\Auth\AdminAccessPolicy.php'
    'app\Models\SystemUpdate.php'
    'app\Http\Controllers\Admin\SystemUpdateController.php'
    'app\Http\Requests\Admin\UploadSystemUpdateRequest.php'
    'app\Http\Requests\Admin\ApplySystemUpdateRequest.php'
    'app\Http\Requests\Admin\RecoverSystemUpdateRequest.php'
    'app\Services\Updates\InspectedUpdatePackage.php'
    'app\Services\Updates\UpdateInstallationLayout.php'
    'app\Services\Updates\UpdatePathPolicy.php'
    'app\Services\Updates\UpdatePackageInspector.php'
    'app\Services\Updates\UpdatePreflight.php'
    'app\Services\Updates\UpdateLog.php'
    'app\Services\Updates\UpdateDatabaseBackup.php'
    'app\Services\Updates\UpdateFilesystemTransaction.php'
    'app\Services\Updates\UpdateLock.php'
    'app\Services\Updates\SystemUpdateInstaller.php'
    'app\Services\Updates\SystemUpdateRecovery.php'
    'database\migrations\2026_07_23_000000_create_system_updates_table.php'
    'database\migrations\2026_07_23_010000_add_execution_state_to_system_updates_table.php'
    'resources\views\admin\settings\system.blade.php'
    'resources\views\admin\settings\_system_tabs.blade.php'
    'resources\views\admin\settings\updates\index.blade.php'
    'resources\views\admin\settings\updates\show.blade.php'
    'resources\views\admin\partials\navigation.blade.php'
    'public\assets\admin\css\app.css'
    'public\account-themes\kaev-aurelia\assets\css\app.css'
    'account-themes\kaev-aurelia\theme.json'
    'deployment\updates\README.md'
    'deployment\updates\build-package.php'
    'deployment\updates\deletions.json'
    'deployment\updates\tests-package-builder.php'
    'deployment\windows\build-web-update-package.ps1'
    'deployment\windows\update.ps1'
    'deployment\windows\support\release-update-support.ps1'
    'deployment\windows\support\composer-audit-support.ps1'
    'deployment\windows\tests\update-workflow.ps1'
    'deployment\windows\tests\composer-audit-policy.ps1'
    'deployment\windows\quality.ps1'
    'deployment\windows\browser-quality.ps1'
    'deployment\windows\build-shared-hosting-package.ps1'
    'deployment\windows\browser-setup.ps1'
    'deployment\windows\security-audit.ps1'
    'deployment\windows\doctor.ps1'
    'deployment\windows\setup.ps1'
    'deployment\windows\serve.ps1'
    'lang\ru.json'
    'lang\en.json'
    'tests\Feature\Updates\SystemUpdateAdminTest.php'
    'tests\Unit\Updates\UpdatePathPolicyTest.php'
    'tests\Unit\Updates\SystemUpdatePhaseTest.php'
    'tests\Unit\Updates\UpdateFilesystemTransactionTest.php'
    'tests\Unit\Updates\UpdateDatabaseBackupTest.php'
    'tests\Unit\Updates\UpdatePackageInspectorTest.php'
    'tests\Feature\WebUpdaterReleaseTest.php'
    'tests\Feature\WebInstallerReleaseTest.php'
    'tests\Unit\TranslationJsonTest.php'
    'resources\views\admin\settings\updates\index.blade.php'
    'tests\Feature\ReleaseMetadataTest.php'
    'tests\Feature\VdsDocumentationReleaseTest.php'
    'tests\Feature\CliSystemUpdaterReleaseTest.php'
)

foreach ($requiredFile in $requiredFiles) {
    $requiredPath = Join-Path $ProjectRoot $requiredFile
    if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
        throw "Patch file is missing: $requiredFile. Re-extract the complete $toVersion patch with file replacement enabled."
    }
}

Write-Host "KaevCMS $fromVersion -> $toVersion update"
Write-Host 'Release quality regressions in formatting, static analysis, and updater tests were corrected.'
Write-Host ''

& (Join-Path $PSScriptRoot 'update.ps1') -SkipTests:$SkipTests

Write-Host ''
Write-Host "KaevCMS $toVersion is ready." -ForegroundColor Green
Write-Host 'Windows setup: .\deployment\windows\setup.ps1'
Write-Host 'Windows quality: .\deployment\windows\quality.ps1'
Write-Host 'Web installer: /install/'
Write-Host 'Shared hosting updater: Administrator panel -> Settings -> System information -> Updates'
Write-Host 'VDS updater: php artisan kaevcms:update C:\path\to\KaevCMS-update.zip'
Write-Host 'Shared hosting package: .\deployment\windows\build-shared-hosting-package.ps1'
Write-Host 'Composer/npm dependencies and database migrations were not changed.'
