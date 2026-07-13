$ErrorActionPreference = 'Continue'

$scriptFile = $PSCommandPath
if ([string]::IsNullOrWhiteSpace($scriptFile)) {
    $scriptFile = $MyInvocation.MyCommand.Path
}

if ([string]::IsNullOrWhiteSpace($scriptFile)) {
    Write-Host '[FAIL] Unable to determine the location of doctor.ps1.' -ForegroundColor Red
    exit 1
}

$script:projectRoot = Split-Path -Parent ([System.IO.Path]::GetFullPath($scriptFile))
Set-Location -LiteralPath $script:projectRoot

$failed = $false
$script:directoryWriteFailures = @()

function Get-ProjectPath {
    param([Parameter(Mandatory = $true)][string]$RelativePath)

    return [System.IO.Path]::GetFullPath((Join-Path $script:projectRoot $RelativePath))
}

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

function Remove-WriteTestFile {
    param([string]$Path)

    if ([string]::IsNullOrWhiteSpace($Path) -or -not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        return
    }

    for ($attempt = 1; $attempt -le 3; $attempt++) {
        try {
            Remove-Item -LiteralPath $Path -Force -ErrorAction Stop
            return
        } catch {
            if ($attempt -lt 3) {
                Start-Sleep -Milliseconds 100
            }
        }
    }

    Write-Host "[WARN] Could not remove diagnostic file: $Path" -ForegroundColor Yellow
}

function Test-DirectoriesWritable {
    param([string[]]$Paths)

    $script:directoryWriteFailures = @()

    foreach ($relativePath in $Paths) {
        $absolutePath = Get-ProjectPath -RelativePath $relativePath

        if (-not (Test-Path -LiteralPath $absolutePath -PathType Container)) {
            $script:directoryWriteFailures += "$relativePath (directory is missing: $absolutePath)"
            continue
        }

        $writeTestPath = Join-Path $absolutePath ('.l2forge-write-test-' + [Guid]::NewGuid().ToString('N') + '.tmp')

        try {
            $utf8 = New-Object System.Text.UTF8Encoding($false)
            [System.IO.File]::WriteAllText($writeTestPath, 'ok', $utf8)

            if (-not (Test-Path -LiteralPath $writeTestPath -PathType Leaf)) {
                throw 'The diagnostic file was not created.'
            }
        } catch {
            $script:directoryWriteFailures += "$relativePath ($($_.Exception.Message))"
        } finally {
            Remove-WriteTestFile -Path $writeTestPath
        }
    }

    return $script:directoryWriteFailures.Count -eq 0
}

$versionPath = Get-ProjectPath -RelativePath 'VERSION'
$versionFilePresent = Test-Path -LiteralPath $versionPath -PathType Leaf
$cmsVersion = if ($versionFilePresent) { (Get-Content -LiteralPath $versionPath -Raw).Trim() } else { 'missing' }
$versionFormatOk = $versionFilePresent -and $cmsVersion -match '^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$'

Write-Host "L2Forge CMS $cmsVersion environment check"
Write-Host "Project: $script:projectRoot"
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

$envPath = Get-ProjectPath -RelativePath '.env'
$autoloadPath = Get-ProjectPath -RelativePath 'vendor\autoload.php'
$databasePath = Get-ProjectPath -RelativePath 'database\database.sqlite'
$bootstrapCachePath = Get-ProjectPath -RelativePath 'bootstrap\cache'
$storageViewsPath = Get-ProjectPath -RelativePath 'storage\framework\views'
$reservedAdminPath = Get-ProjectPath -RelativePath 'public\admin'

Test-ItemStatus '.env file' (Test-Path -LiteralPath $envPath -PathType Leaf) $(if (Test-Path -LiteralPath $envPath -PathType Leaf) { 'present' } else { 'missing' })
Test-ItemStatus 'Composer dependencies' (Test-Path -LiteralPath $autoloadPath -PathType Leaf) $(if (Test-Path -LiteralPath $autoloadPath -PathType Leaf) { 'installed' } else { 'run .\setup.ps1' })
Test-ItemStatus 'SQLite database' (Test-Path -LiteralPath $databasePath -PathType Leaf) $(if (Test-Path -LiteralPath $databasePath -PathType Leaf) { 'present' } else { 'missing' })
Test-ItemStatus 'Bootstrap cache directory' (Test-Path -LiteralPath $bootstrapCachePath -PathType Container) $(if (Test-Path -LiteralPath $bootstrapCachePath -PathType Container) { 'present' } else { 'missing' })
Test-ItemStatus 'Storage views directory' (Test-Path -LiteralPath $storageViewsPath -PathType Container) $(if (Test-Path -LiteralPath $storageViewsPath -PathType Container) { 'present' } else { 'missing' })
Test-ItemStatus 'Reserved public/admin path' (-not (Test-Path -LiteralPath $reservedAdminPath)) $(if (Test-Path -LiteralPath $reservedAdminPath) { 'conflicts with the /admin Laravel route; move assets to public\assets\admin' } else { 'not present' })

$newsUploadPaths = @(
    'public\uploads\news\covers',
    'public\uploads\news\content'
)
$newsUploadWritable = Test-DirectoriesWritable -Paths $newsUploadPaths
$newsUploadFailures = @($script:directoryWriteFailures)
$missingNewsUploadPaths = @($newsUploadPaths | Where-Object { -not (Test-Path -LiteralPath (Get-ProjectPath -RelativePath $_) -PathType Container) })
$newsUploadDetails = if ($missingNewsUploadPaths.Count -gt 0) {
    'missing: ' + ($missingNewsUploadPaths -join ', ') + '; run .\setup.ps1 or .\update.ps1'
} elseif ($newsUploadWritable) {
    'cover and content directories are writable'
} else {
    'write failed: ' + ($newsUploadFailures -join '; ')
}
Test-ItemStatus 'News upload directories' $newsUploadWritable $newsUploadDetails

$settingsUploadPaths = @(
    'public\uploads\settings\logo',
    'public\uploads\settings\favicon'
)
$settingsUploadWritable = Test-DirectoriesWritable -Paths $settingsUploadPaths
$settingsUploadFailures = @($script:directoryWriteFailures)
$missingSettingsUploadPaths = @($settingsUploadPaths | Where-Object { -not (Test-Path -LiteralPath (Get-ProjectPath -RelativePath $_) -PathType Container) })
$settingsUploadDetails = if ($missingSettingsUploadPaths.Count -gt 0) {
    'missing: ' + ($missingSettingsUploadPaths -join ', ') + '; run .\setup.ps1 or .\update.ps1'
} elseif ($settingsUploadWritable) {
    'logo and favicon directories are writable'
} else {
    'write failed: ' + ($settingsUploadFailures -join '; ')
}
Test-ItemStatus 'Settings upload directories' $settingsUploadWritable $settingsUploadDetails

if ((Test-Path -LiteralPath $autoloadPath -PathType Leaf) -and (Test-Path -LiteralPath $envPath -PathType Leaf)) {
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
