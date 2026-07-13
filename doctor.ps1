$ErrorActionPreference = 'Continue'
Set-Location $PSScriptRoot

$failed = $false

function Test-ItemStatus {
    param(
        [string]$Label,
        [bool]$Ok,
        [string]$Details
    )

    if ($Ok) {
        Write-Host "[OK]   $Label - $Details" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] $Label - $Details" -ForegroundColor Red
        $script:failed = $true
    }
}

function Test-DirectoriesWritable {
    param([string[]]$Paths)

    foreach ($path in $Paths) {
        if (-not (Test-Path $path)) {
            return $false
        }

        try {
            $writeTestPath = Join-Path $path ('.l2forge-write-test-' + [Guid]::NewGuid().ToString('N'))
            [System.IO.File]::WriteAllText($writeTestPath, 'ok')
            Remove-Item $writeTestPath -Force
        } catch {
            return $false
        }
    }

    return $true
}

$versionFilePresent = Test-Path 'VERSION'
$cmsVersion = if ($versionFilePresent) { (Get-Content 'VERSION' -Raw).Trim() } else { 'missing' }
$versionFormatOk = $versionFilePresent -and $cmsVersion -match '^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$'

Write-Host "L2Forge CMS $cmsVersion environment check"
Write-Host "Project: $PSScriptRoot"
Write-Host ''

Test-ItemStatus 'VERSION file' $versionFormatOk $(if (-not $versionFilePresent) { 'missing' } elseif (-not $versionFormatOk) { "invalid value: $cmsVersion" } else { $cmsVersion })

$phpCommand = Get-Command php -ErrorAction SilentlyContinue
Test-ItemStatus 'PHP command' ($null -ne $phpCommand) $(if ($phpCommand) { $phpCommand.Source } else { 'not found in PATH' })

if ($phpCommand) {
    $phpVersionText = (& php -r "echo PHP_VERSION;").Trim()
    $versionOk = $false
    try { $versionOk = ([Version]$phpVersionText -ge [Version]'8.3.0') } catch {}
    Test-ItemStatus 'PHP version' $versionOk $phpVersionText

    $requiredExtensions = @('ctype', 'dom', 'fileinfo', 'mbstring', 'openssl', 'pdo', 'pdo_sqlite', 'tokenizer', 'xml')
    $loadedExtensions = & php -r "echo implode(PHP_EOL, get_loaded_extensions());"
    foreach ($extension in $requiredExtensions) {
        Test-ItemStatus "PHP extension $extension" ($loadedExtensions -contains $extension) $(if ($loadedExtensions -contains $extension) { 'loaded' } else { 'missing' })
    }
}

$composerCommand = Get-Command composer -ErrorAction SilentlyContinue
Test-ItemStatus 'Composer command' ($null -ne $composerCommand) $(if ($composerCommand) { $composerCommand.Source } else { 'not found in PATH' })

Test-ItemStatus '.env file' (Test-Path '.env') $(if (Test-Path '.env') { 'present' } else { 'missing' })
Test-ItemStatus 'Composer dependencies' (Test-Path 'vendor\autoload.php') $(if (Test-Path 'vendor\autoload.php') { 'installed' } else { 'run .\setup.ps1' })
Test-ItemStatus 'SQLite database' (Test-Path 'database\database.sqlite') $(if (Test-Path 'database\database.sqlite') { 'present' } else { 'missing' })
Test-ItemStatus 'Bootstrap cache directory' (Test-Path 'bootstrap\cache') $(if (Test-Path 'bootstrap\cache') { 'present' } else { 'missing' })
Test-ItemStatus 'Storage views directory' (Test-Path 'storage\framework\views') $(if (Test-Path 'storage\framework\views') { 'present' } else { 'missing' })
Test-ItemStatus 'Reserved public/admin path' (-not (Test-Path 'public\admin')) $(if (Test-Path 'public\admin') { 'conflicts with the /admin Laravel route; move assets to public\assets\admin' } else { 'not present' })
$newsUploadPaths = @(
    'public\uploads\news\covers',
    'public\uploads\news\content'
)
$newsUploadWritable = Test-DirectoriesWritable -Paths $newsUploadPaths
$missingNewsUploadPaths = @($newsUploadPaths | Where-Object { -not (Test-Path $_) })
$newsUploadDetails = if ($missingNewsUploadPaths.Count -gt 0) {
    'missing: ' + ($missingNewsUploadPaths -join ', ') + '; run .\setup.ps1 or .\update.ps1'
} elseif ($newsUploadWritable) {
    'cover and content directories are writable'
} else {
    'one or more media directories are not writable'
}
Test-ItemStatus 'News upload directories' $newsUploadWritable $newsUploadDetails

$settingsUploadPaths = @(
    'public\uploads\settings\logo',
    'public\uploads\settings\favicon'
)
$settingsUploadWritable = Test-DirectoriesWritable -Paths $settingsUploadPaths
$missingSettingsUploadPaths = @($settingsUploadPaths | Where-Object { -not (Test-Path $_) })
$settingsUploadDetails = if ($missingSettingsUploadPaths.Count -gt 0) {
    'missing: ' + ($missingSettingsUploadPaths -join ', ') + '; run .\setup.ps1 or .\update.ps1'
} elseif ($settingsUploadWritable) {
    'logo and favicon directories are writable'
} else {
    'one or more image directories are not writable'
}
Test-ItemStatus 'Settings upload directories' $settingsUploadWritable $settingsUploadDetails

if ((Test-Path 'vendor\autoload.php') -and (Test-Path '.env')) {
    Write-Host ''
    Write-Host 'Laravel check:'
    php artisan about --only=environment,cache,drivers
    if ($LASTEXITCODE -ne 0) {
        $failed = $true
    }
}

Write-Host ''
if ($failed) {
    Write-Host 'One or more checks failed.' -ForegroundColor Red
    exit 1
}

Write-Host 'All checks passed.' -ForegroundColor Green
exit 0
