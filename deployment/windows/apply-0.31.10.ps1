param(
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
Set-Location -LiteralPath $ProjectRoot

$fromVersion = '0.31.9'
$toVersion = '0.31.10'

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
    'CHANGELOG.md',
    'README.md',
    'VERSION',
    'public\index.php',
    'public\.htaccess',
    'public\install\index.php',
    'deployment\hosting\README.md',
    'docs\WEB_INSTALLER.md',
    'docs\ROADMAP.md',
    'deployment\hosting\web-installer\installer.php',
    'deployment\hosting\web-installer\tests\installer-regression.php',
    'deployment\windows\README.md',
    'deployment\windows\update.ps1',
    'deployment\windows\support\release-update-support.ps1',
    'deployment\windows\support\composer-audit-support.ps1',
    'deployment\windows\tests\update-workflow.ps1',
    'deployment\windows\tests\composer-audit-policy.ps1',
    'deployment\windows\quality.ps1',
    'deployment\windows\browser-quality.ps1',
    'deployment\windows\browser-setup.ps1',
    'deployment\windows\security-audit.ps1',
    'deployment\windows\doctor.ps1',
    'deployment\windows\setup.ps1',
    'deployment\windows\serve.ps1',
    'tests\Feature\WebInstallerReleaseTest.php',
    'tests\Feature\ReleaseMetadataTest.php',
    'tests\Feature\Account\GameAccountCabinetTest.php',
    'tests\Feature\BundledAureliaThemesTest.php',
    'tests\Feature\BrowserDependencyLockTest.php'
)

foreach ($requiredFile in $requiredFiles) {
    $requiredPath = Join-Path $ProjectRoot $requiredFile
    if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
        throw "Patch file is missing: $requiredFile. Re-extract the complete $toVersion patch with file replacement enabled."
    }
}

Write-Host "KaevCMS $fromVersion -> $toVersion update"
Write-Host 'Windows tools now live in deployment\windows; the web installer is available at /install/.'
Write-Host ''

& (Join-Path $PSScriptRoot 'update.ps1') -SkipTests:$SkipTests

Write-Host ''
Write-Host "KaevCMS $toVersion is ready." -ForegroundColor Green
Write-Host 'Windows setup: .\deployment\windows\setup.ps1'
Write-Host 'Windows quality: .\deployment\windows\quality.ps1'
Write-Host 'Web installer: /install/'
Write-Host 'Composer/npm dependencies and database migrations were not changed.'
