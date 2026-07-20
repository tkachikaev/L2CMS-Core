param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$fromVersion = '0.23.12'
$toVersion = '0.24.0'

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
    'bootstrap\app.php',
    'bootstrap\providers.php',
    'config\cms.php',
    'app\Auth\AdminAccessPolicy.php',
    'app\Auth\AdminPermission.php',
    'app\Auth\AdminRole.php',
    'app\Http\Controllers\Admin\ModuleController.php',
    'app\Http\Middleware\EnsureModuleEnabled.php',
    'app\Models\AuditLog.php',
    'app\Models\ModuleState.php',
    'app\Providers\ModuleServiceProvider.php',
    'app\Support\Modules\ModuleContext.php',
    'app\Support\Modules\ModuleManager.php',
    'app\Support\Modules\ModuleRuntime.php',
    'app\Support\Modules\ModuleValidator.php',
    'database\migrations\2026_07_20_000200_create_cms_modules_table.php',
    'routes\admin.php',
    'resources\schemas\module.schema.json',
    'resources\views\admin\modules\index.blade.php',
    'resources\views\admin\partials\navigation.blade.php',
    'public\assets\admin\css\app.css',
    'public\account-themes\kaev-aurelia\assets\css\app.css',
    'lang\ru.json',
    'lang\en.json',
    'modules\README.md',
    'docs\MODULES.md',
    'docs\ROADMAP.md',
    'tests\Feature\Admin\AdminPanelTest.php',
    'tests\Feature\BundledAureliaThemesTest.php',
    'tests\Feature\Modules\ModuleFoundationTest.php',
    'tests\Feature\ReleaseMetadataTest.php',
    'tests\Unit\Auth\AdminRoleTest.php',
    'tests\browser\specs\admin-navigation.spec.mjs',
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
