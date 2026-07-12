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

Write-Host 'L2Forge CMS environment check'
Write-Host "Project: $PSScriptRoot"
Write-Host ''

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
$newsUploadPath = 'public\uploads\news'
$newsUploadWritable = $false
if (Test-Path $newsUploadPath) {
    try {
        $writeTestPath = Join-Path $newsUploadPath ('.l2forge-write-test-' + [Guid]::NewGuid().ToString('N'))
        [System.IO.File]::WriteAllText($writeTestPath, 'ok')
        Remove-Item $writeTestPath -Force
        $newsUploadWritable = $true
    } catch {
        $newsUploadWritable = $false
    }
}
Test-ItemStatus 'News upload directory' $newsUploadWritable $(if (-not (Test-Path $newsUploadPath)) { 'missing; run .\setup.ps1 or .\update.ps1' } elseif ($newsUploadWritable) { 'present and writable' } else { 'present but not writable' })

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
