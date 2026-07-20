<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReleaseMetadataTest extends TestCase
{
    public function test_release_metadata_matches_version_file(): void
    {
        $version = trim($this->readReleaseFile('VERSION'));

        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',
            $version
        );

        $readme = $this->normalized($this->readReleaseFile('README.md'));
        $this->assertStringStartsWith("# KaevCMS {$version}\n", $readme);

        $changelog = $this->normalized($this->readReleaseFile('CHANGELOG.md'));
        $matched = preg_match(
            '/^##\s+(\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?)\s+-\s+\d{4}-\d{2}-\d{2}\s*$/m',
            $changelog,
            $matches
        );

        $this->assertSame(1, $matched, 'CHANGELOG must start with a dated release heading.');
        $this->assertSame($version, $matches[1] ?? null);

        $updateScript = $this->readReleaseFile('update.ps1');
        $this->assertStringContainsString("\$cmsVersion = (Get-Content 'VERSION' -Raw).Trim()", $updateScript);
        $this->assertStringContainsString('Write-UpdateStage -Message "KaevCMS $expectedFromVersion -> $cmsVersion update"', $updateScript);

        $applyScripts = glob(base_path('apply-*.ps1')) ?: [];
        sort($applyScripts);

        $this->assertCount(1, $applyScripts, 'A release must contain exactly one current apply script.');
        $this->assertSame("apply-{$version}.ps1", basename($applyScripts[0]));

        $applyScript = (string) file_get_contents($applyScripts[0]);
        $this->assertStringContainsString("\$toVersion = '{$version}'", $applyScript);
        $this->assertStringContainsString("\$fromVersion = '0.23.12'", $applyScript);
        $this->assertStringContainsString("'app\\Providers\\ModuleServiceProvider.php'", $applyScript);
        $this->assertStringContainsString("'app\\Http\\Middleware\\EnsureModuleEnabled.php'", $applyScript);
        $this->assertStringContainsString("'app\\Support\\Modules\\ModuleManager.php'", $applyScript);
        $this->assertStringContainsString("'app\\Support\\Modules\\ModuleRuntime.php'", $applyScript);
        $this->assertStringContainsString("'app\\Support\\Modules\\ModuleValidator.php'", $applyScript);
        $this->assertStringContainsString("'database\\migrations\\2026_07_20_000200_create_cms_modules_table.php'", $applyScript);
        $this->assertStringContainsString("'resources\\schemas\\module.schema.json'", $applyScript);
        $this->assertStringContainsString("'resources\\views\\admin\\modules\\index.blade.php'", $applyScript);
        $this->assertStringContainsString("'docs\\MODULES.md'", $applyScript);
        $this->assertStringContainsString("'tests\\Feature\\Modules\\ModuleFoundationTest.php'", $applyScript);
        $this->assertStringContainsString("'tests\\Feature\\BundledAureliaThemesTest.php'", $applyScript);
        $this->assertStringNotContainsString('Remove-Item -LiteralPath $obsoleteApplyScript.FullName', $applyScript);
        $this->assertStringNotContainsString('update.ps1 failed with exit code $LASTEXITCODE', $applyScript);
    }

    public function test_update_script_verifies_source_preserves_env_and_stages_cleanup_before_tests(): void
    {
        $updateScript = $this->readReleaseFile('update.ps1');

        $this->assertStringContainsString("\$expectedFromVersion = '0.23.12'", $updateScript);
        $this->assertStringContainsString("\$expectedToVersion = '0.24.0'", $updateScript);
        $this->assertStringContainsString("\$legacyApplyScriptName = 'apply-0.23.12.ps1'", $updateScript);
        $this->assertStringContainsString("\$legacyApplySha256 = 'e564cd67e2ecb7cfd5666cbd9099365903c8aab8f2988b41c6120e28425fae44'", $updateScript);
        $this->assertStringContainsString('Get-KaevCmsInstalledVersion', $updateScript);
        $this->assertStringContainsString('-ExpectedToVersion $expectedToVersion', $updateScript);
        $this->assertStringContainsString('legacyApplySha256', $updateScript);
        $this->assertStringContainsString('Write-KaevCmsPendingUpdateMarker', $updateScript);
        $this->assertStringContainsString('Move-KaevCmsArtifactsToBackup', $updateScript);
        $this->assertStringContainsString('Remove-KaevCmsUpdateBackups', $updateScript);
        $this->assertStringNotContainsString('QUEUE_CONNECTION=sync', $updateScript);
        $this->assertStringNotContainsString('SESSION_COOKIE=l2forge_session', $updateScript);
        $this->assertStringNotContainsString('function Set-EnvValue', $updateScript);
        $this->assertStringContainsString('Clear-KaevCmsBootstrapCache -ProjectRoot $PSScriptRoot', $updateScript);
        $this->assertStringContainsString('composer install --no-interaction --prefer-dist --no-scripts', $updateScript);
        $this->assertStringContainsString('$composerDependenciesChanged', $updateScript);
        $this->assertStringContainsString('Composer install was skipped', $updateScript);
        $this->assertStringContainsString('$actualComposerLockSha256 -ne $currentComposerLockSha256', $updateScript);
        $this->assertStringContainsString('php artisan queue:restart', $updateScript);
        $this->assertStringContainsString('php artisan kaevcms:maintenance-status --no-ansi', $updateScript);
        $this->assertStringContainsString('php artisan down --retry=60', $updateScript);
        $this->assertStringContainsString('finally {', $updateScript);
        $this->assertStringContainsString('php artisan up', $updateScript);
        $this->assertStringContainsString('php artisan kaevcms:release-version --mark=$cmsVersion', $updateScript);
        $this->assertStringContainsString("'resources\\views\\account'", $updateScript);
        $this->assertStringContainsString("'resources\\views\\livewire\\account'", $updateScript);
        $this->assertStringContainsString("'public\\assets\\account'", $updateScript);

        $cachePosition = strpos($updateScript, 'Clear-KaevCmsBootstrapCache -ProjectRoot $PSScriptRoot');
        $maintenancePosition = strpos($updateScript, 'php artisan down --retry=60');
        $composerPosition = strpos($updateScript, 'composer install --no-interaction --prefer-dist --no-scripts');
        $migrationPosition = strpos($updateScript, 'php artisan migrate --force');
        $queueRestartPosition = strpos($updateScript, 'php artisan queue:restart');
        $stagePosition = strpos($updateScript, 'Move-KaevCmsArtifactsToBackup');
        $testPosition = strpos($updateScript, 'php artisan test');
        $markPosition = strpos($updateScript, 'php artisan kaevcms:release-version --mark=$cmsVersion');
        $backupCleanupPosition = strpos($updateScript, 'Remove-KaevCmsUpdateBackups', $markPosition ?: 0);
        $finalCleanupPosition = strpos($updateScript, 'Remove-ObsoleteReleaseArtifacts -CurrentVersion $cmsVersion', $testPosition ?: 0);

        $this->assertNotFalse($cachePosition);
        $this->assertNotFalse($maintenancePosition);
        $this->assertNotFalse($composerPosition);
        $this->assertNotFalse($migrationPosition);
        $this->assertNotFalse($queueRestartPosition);
        $this->assertNotFalse($stagePosition);
        $this->assertNotFalse($testPosition);
        $this->assertNotFalse($markPosition);
        $this->assertNotFalse($backupCleanupPosition);
        $this->assertNotFalse($finalCleanupPosition);
        $this->assertLessThan($composerPosition, $cachePosition);
        $this->assertLessThan($composerPosition, $maintenancePosition);
        $this->assertLessThan($queueRestartPosition, $migrationPosition);
        $this->assertLessThan($testPosition, $queueRestartPosition);
        $this->assertLessThan($testPosition, $stagePosition);
        $this->assertLessThan($markPosition, $testPosition);
        $this->assertLessThan($backupCleanupPosition, $markPosition);
        $this->assertLessThan($finalCleanupPosition, $testPosition);

        $phpunit = $this->readReleaseFile('phpunit.xml');
        $this->assertStringContainsString('<env name="APP_MAINTENANCE_DRIVER" value="cache" force="true"/>', $phpunit);
        $this->assertStringContainsString('<env name="APP_MAINTENANCE_STORE" value="array" force="true"/>', $phpunit);
        $this->assertStringNotContainsString('<env name="APP_MAINTENANCE_DRIVER" value="file"/>', $phpunit);

        $doctorScript = $this->readReleaseFile('doctor.ps1');
        $this->assertStringContainsString('php artisan kaevcms:release-version --no-ansi', $doctorScript);
        $this->assertStringContainsString('php artisan kaevcms:encryption-health --no-ansi', $doctorScript);

        $qualityScript = $this->readReleaseFile('quality.ps1');
        $this->assertStringContainsString('tests\\powershell\\update-workflow.ps1', $qualityScript);
        $this->assertStringContainsString('tests\\powershell\\composer-audit-policy.ps1', $qualityScript);
        $this->assertStringContainsString('$env:COMPOSER_DISABLE_NETWORK = \'1\'', $qualityScript);
        $this->assertStringContainsString('Remove-Item Env:COMPOSER_DISABLE_NETWORK', $qualityScript);
        $this->assertStringContainsString('finally {', $qualityScript);
        $this->assertStringNotContainsString('Invoke-KaevCmsComposerSecurityAudit', $qualityScript);
        $this->assertStringContainsString('php artisan route:cache', $qualityScript);
        $this->assertSame(2, substr_count($qualityScript, 'php artisan route:clear'));

        $securityAuditScript = $this->readReleaseFile('security-audit.ps1');
        $this->assertStringContainsString('scripts\\composer-audit-support.ps1', $securityAuditScript);
        $this->assertStringContainsString('Invoke-KaevCmsComposerSecurityAudit', $securityAuditScript);
        $this->assertStringContainsString('npm audit --audit-level=high', $securityAuditScript);

        $composerAuditSupport = $this->readReleaseFile('scripts/composer-audit-support.ps1');
        $this->assertStringContainsString(
            '$composerExecutable audit --locked --no-interaction',
            $composerAuditSupport,
        );
        $this->assertStringContainsString('Test-KaevCmsComposerAuditNetworkFailure', $composerAuditSupport);
        $this->assertStringContainsString('PSNativeCommandUseErrorActionPreference', $composerAuditSupport);
        $this->assertStringContainsString('Remove-Item Env:COMPOSER_DISABLE_NETWORK', $composerAuditSupport);
        $this->assertStringContainsString('System.Management.Automation.ErrorRecord', $composerAuditSupport);
        $this->assertStringContainsString('Dependency security has not been verified', $composerAuditSupport);
        $this->assertStringContainsString('throw "Composer security audit failed with exit code $auditExitCode."', $composerAuditSupport);

        $composerAuditPolicyTest = $this->readReleaseFile('tests/powershell/composer-audit-policy.ps1');
        $this->assertStringContainsString('curl error 28', $composerAuditPolicyTest);
        $this->assertStringContainsString('security vulnerability advisory', $composerAuditPolicyTest);
        $this->assertStringContainsString('No security vulnerability advisories found.', $composerAuditPolicyTest);
        $this->assertStringContainsString('Network disabled, request canceled.', $composerAuditPolicyTest);
        $this->assertStringContainsString('NativeCommandError', $composerAuditPolicyTest);

        $browserQualityScript = $this->readReleaseFile('browser-quality.ps1');
        $this->assertStringContainsString('node --test tests/browser/support/navigation.test.mjs', $browserQualityScript);
        $this->assertStringContainsString('npm run test:browser', $browserQualityScript);
        $this->assertStringNotContainsString('npm ci', $browserQualityScript);
        $this->assertStringNotContainsString('npm audit', $browserQualityScript);
        $this->assertStringNotContainsString('playwright install', $browserQualityScript);

        $browserSetupScript = $this->readReleaseFile('browser-setup.ps1');
        $this->assertStringContainsString('npm ci', $browserSetupScript);
        $this->assertStringContainsString('playwright install chromium', $browserSetupScript);

        $browserRunner = $this->readReleaseFile('tests/browser/run.mjs');
        $this->assertStringContainsString('findAvailablePort', $browserRunner);
        $this->assertStringContainsString('`--port=${browserPort}`', $browserRunner);

        $browserNavigation = $this->readReleaseFile('tests/browser/support/navigation.mjs');
        $this->assertStringContainsString('net::ERR_NO_BUFFER_SPACE', $browserNavigation);
        $this->assertStringContainsString('attempt <= 3', $browserNavigation);

        $browserNavigationTest = $this->readReleaseFile('tests/browser/support/navigation.test.mjs');
        $this->assertStringContainsString('ERR_NO_BUFFER_SPACE', $browserNavigationTest);
        $this->assertStringContainsString('does not retry application or unrelated browser failures', $browserNavigationTest);

        $workflow = $this->readReleaseFile('.github/workflows/quality.yml');
        $this->assertStringContainsString('composer audit --locked --no-interaction', $workflow);
        $this->assertStringContainsString('npm audit --audit-level=high', $workflow);
    }

    public function test_module_foundation_release_artifacts_are_shipped(): void
    {
        $this->assertFileExists(app_path('Providers/ModuleServiceProvider.php'));
        $this->assertFileExists(app_path('Http/Middleware/EnsureModuleEnabled.php'));
        $this->assertFileExists(app_path('Support/Modules/ModuleManager.php'));
        $this->assertFileExists(app_path('Support/Modules/ModuleRuntime.php'));
        $this->assertFileExists(app_path('Support/Modules/ModuleValidator.php'));
        $this->assertFileExists(database_path('migrations/2026_07_20_000200_create_cms_modules_table.php'));
        $this->assertFileExists(resource_path('schemas/module.schema.json'));
        $this->assertFileExists(resource_path('views/admin/modules/index.blade.php'));
        $this->assertFileExists(base_path('modules/README.md'));
        $this->assertFileExists(base_path('docs/MODULES.md'));

        $schema = json_decode(
            $this->readReleaseFile('resources/schemas/module.schema.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertFalse($schema['additionalProperties']);
        $this->assertSame(['schema', 'id', 'name', 'version', 'author'], $schema['required']);
        $this->assertSame(1, $schema['properties']['schema']['const']);

        $runtime = $this->readReleaseFile('app/Support/Modules/ModuleRuntime.php');
        $this->assertStringContainsString("array_intersect(['route:cache', 'optimize'], \$arguments)", $runtime);

        $aureliaCss = $this->readReleaseFile('public/account-themes/kaev-aurelia/assets/css/app.css');
        $this->assertStringContainsString('display: grid; place-items: center;', $aureliaCss);
        $this->assertStringContainsString('.account-character-avatar > span', $aureliaCss);
    }

    public function test_obsolete_preview_and_settings_placeholder_are_not_shipped(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('preview'));
        $this->assertFileDoesNotExist(resource_path('views/admin/settings/placeholder.blade.php'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/Admin/SettingsController.php'));
        $this->assertFileExists(base_path('routes/public.php'));
        $this->assertFileExists(base_path('routes/account.php'));
        $this->assertFileExists(base_path('routes/admin.php'));
    }

    private function readReleaseFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        if ($contents === false) {
            $this->fail("Unable to read release file: {$path}");
        }

        return $contents;
    }

    private function normalized(string $contents): string
    {
        return str_replace("\r\n", "\n", $contents);
    }
}
