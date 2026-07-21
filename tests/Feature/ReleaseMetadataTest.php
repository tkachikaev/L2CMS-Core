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
        $this->assertStringContainsString("\$fromVersion = '0.25.1'", $applyScript);
        $this->assertStringContainsString("'app\\Jobs\\ConfirmRewardDelivery.php'", $applyScript);
        $this->assertStringContainsString("'app\\Services\\GameWorld\\MobiusInterludeGameWorldDriver.php'", $applyScript);
        $this->assertStringContainsString("'app\\Services\\Rewards\\RewardDeliveryProcessor.php'", $applyScript);
        $this->assertStringContainsString("'integrations\\mobius-interlude\\reward-bridge\\CharacterSelect.official.sha256'", $applyScript);
        $this->assertStringContainsString("'integrations\\mobius-interlude\\reward-bridge\\CharacterSelect.patch'", $applyScript);
        $this->assertStringContainsString("'integrations\\mobius-interlude\\reward-bridge\\install.sql'", $applyScript);
        $this->assertStringContainsString("'integrations\\mobius-interlude\\reward-bridge\\KaevRewardBridge.java'", $applyScript);
        $this->assertStringContainsString("'integrations\\mobius-interlude\\reward-bridge\\KaevRewardDeliveryLock.java'", $applyScript);
        $this->assertStringContainsString("'docs\\WEB_INVENTORY.md'", $applyScript);
        $this->assertStringContainsString("'tests\\Feature\\Rewards\\MobiusRewardBridgeDriverTest.php'", $applyScript);
        $this->assertStringContainsString("'tests\\Feature\\Rewards\\WebInventoryTest.php'", $applyScript);
        $this->assertStringContainsString("'tests\\powershell\\update-workflow.ps1'", $applyScript);
        $this->assertStringNotContainsString('Remove-Item -LiteralPath $obsoleteApplyScript.FullName', $applyScript);
        $this->assertStringNotContainsString('update.ps1 failed with exit code $LASTEXITCODE', $applyScript);
    }

    public function test_update_script_verifies_source_preserves_env_and_stages_cleanup_before_tests(): void
    {
        $updateScript = $this->readReleaseFile('update.ps1');

        $this->assertStringContainsString("\$expectedFromVersion = '0.25.1'", $updateScript);
        $this->assertStringContainsString("\$expectedToVersion = '0.25.2'", $updateScript);
        $this->assertStringContainsString("\$legacyApplyScriptName = 'apply-0.25.1.ps1'", $updateScript);
        $this->assertStringContainsString("\$legacyApplySha256 = '33cd3c5a7ddb12a0e3e43ac7675d92fae83195b8d28cd42690fd1ead7cd4f5cb'", $updateScript);
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

        $bridgeSql = $this->readReleaseFile('integrations/mobius-interlude/reward-bridge/install.sql');
        $this->assertStringContainsString("enum('pending','processing','delivered','failed','uncertain')", $bridgeSql);
        $this->assertStringContainsString('kaev_reward_operation_items', $bridgeSql);

        $bridgeJava = $this->readReleaseFile('integrations/mobius-interlude/reward-bridge/KaevRewardBridge.java');
        $this->assertStringContainsString('mobius_reward_bridge_v2', $bridgeJava);
        $this->assertStringContainsString('KaevRewardDeliveryLock.getLock(operation.characterId)', $bridgeJava);
        $this->assertStringContainsString('IdManager.getInstance().getNextId()', $bridgeJava);
        $this->assertStringContainsString("status = 'uncertain'", $bridgeJava);
        $this->assertStringNotContainsString('releaseId(', $bridgeJava);
        $this->assertStringNotContainsString('MAX(object_id)', $bridgeJava);

        $deliveryLock = $this->readReleaseFile('integrations/mobius-interlude/reward-bridge/KaevRewardDeliveryLock.java');
        $this->assertStringContainsString('ConcurrentHashMap<Integer, ReentrantLock>', $deliveryLock);
        $this->assertStringContainsString('computeIfAbsent', $deliveryLock);

        $characterSelectPatch = $this->readReleaseFile('integrations/mobius-interlude/reward-bridge/CharacterSelect.patch');
        $this->assertStringContainsString('KaevRewardDeliveryLock.getLock(info.getObjectId())', $characterSelectPatch);
        $this->assertStringContainsString('cha.setOnlineStatus(true, true);', $characterSelectPatch);
        $this->assertStringContainsString('rewardDeliveryLock.unlock();', $characterSelectPatch);

        $characterSelectSha = trim($this->readReleaseFile('integrations/mobius-interlude/reward-bridge/CharacterSelect.official.sha256'));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $characterSelectSha);

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
        $this->assertFileExists(app_path('Models/ModuleMigration.php'));
        $this->assertFileExists(app_path('Support/Modules/ModuleManager.php'));
        $this->assertFileExists(app_path('Support/Modules/ModuleMigrationManager.php'));
        $this->assertFileExists(app_path('Support/Modules/ModuleRuntime.php'));
        $this->assertFileExists(app_path('Support/Modules/ModuleValidator.php'));
        $this->assertFileExists(database_path('migrations/2026_07_20_000200_create_cms_modules_table.php'));
        $this->assertFileExists(database_path('migrations/2026_07_21_000000_add_module_migration_lifecycle.php'));
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
        $this->assertSame('#/$defs/relativePath', $schema['properties']['migrations']['$ref']);

        $migrationManager = $this->readReleaseFile('app/Support/Modules/ModuleMigrationManager.php');
        $this->assertStringContainsString('Cache::lock', $migrationManager);
        $this->assertStringContainsString("hash_file('sha256'", $migrationManager);
        $this->assertStringContainsString('rollbackCurrentRun', $migrationManager);

        $moduleManager = $this->readReleaseFile('app/Support/Modules/ModuleManager.php');
        $this->assertStringContainsString("'migration_pending'", $moduleManager);
        $this->assertStringContainsString("'migration_modified'", $moduleManager);
        $this->assertStringContainsString("'migration_error'", $moduleManager);

        $runtime = $this->readReleaseFile('app/Support/Modules/ModuleRuntime.php');
        $this->assertStringContainsString("array_intersect(['route:cache', 'optimize'], \$arguments)", $runtime);

        $aureliaCss = $this->readReleaseFile('public/account-themes/kaev-aurelia/assets/css/app.css');
        $this->assertStringContainsString('display: grid; place-items: center;', $aureliaCss);
        $this->assertStringContainsString('.account-character-avatar > span', $aureliaCss);
        $this->assertStringContainsString('.account-surface {', $aureliaCss);

        $aureliaInventory = $this->readReleaseFile('account-themes/kaev-aurelia/views/web-inventory/index.blade.php');
        $this->assertStringContainsString('account-surface reward-inventory-shell', $aureliaInventory);
    }

    public function test_web_inventory_release_artifacts_are_shipped(): void
    {
        $this->assertFileExists(app_path('Contracts/GameRewardDeliveryGateway.php'));
        $this->assertFileExists(app_path('Jobs/ConfirmRewardDelivery.php'));
        $this->assertFileExists(app_path('Jobs/ProcessRewardDelivery.php'));
        $this->assertFileExists(app_path('Models/RewardInventoryGrant.php'));
        $this->assertFileExists(app_path('Models/RewardInventoryItem.php'));
        $this->assertFileExists(app_path('Models/RewardDelivery.php'));
        $this->assertFileExists(app_path('Services/Rewards/RewardInventoryService.php'));
        $this->assertFileExists(app_path('Services/Rewards/RewardDeliveryProcessor.php'));
        $this->assertFileExists(database_path('migrations/2026_07_21_000100_create_reward_inventory_tables.php'));
        $this->assertFileExists(base_path('docs/WEB_INVENTORY.md'));
        $this->assertFileExists(base_path('integrations/mobius-interlude/reward-bridge/CharacterSelect.official.sha256'));
        $this->assertFileExists(base_path('integrations/mobius-interlude/reward-bridge/CharacterSelect.patch'));
        $this->assertFileExists(base_path('integrations/mobius-interlude/reward-bridge/install.sql'));
        $this->assertFileExists(base_path('integrations/mobius-interlude/reward-bridge/KaevRewardBridge.java'));
        $this->assertFileExists(base_path('integrations/mobius-interlude/reward-bridge/KaevRewardDeliveryLock.java'));
        $this->assertFileExists(base_path('account-themes/kaev-aurelia/views/web-inventory/index.blade.php'));
        $this->assertFileExists(base_path('account-themes/luxury/views/web-inventory/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/rewards/index.blade.php'));

        $contract = $this->readReleaseFile('app/Contracts/GameWorldDriver.php');
        $this->assertStringContainsString('rewardDeliveryCapabilities', $contract);
        $this->assertStringContainsString('deliverRewards', $contract);
        $this->assertStringContainsString('rewardDeliveryStatus', $contract);

        $mobiusDriver = $this->readReleaseFile('app/Services/GameWorld/MobiusInterludeGameWorldDriver.php');
        $this->assertStringNotContainsString("table('items')", $mobiusDriver);

        $migration = $this->readReleaseFile('database/migrations/2026_07_21_000100_create_reward_inventory_tables.php');
        $this->assertStringContainsString("Schema::create('reward_inventory_grants'", $migration);
        $this->assertStringContainsString("Schema::create('reward_inventory_items'", $migration);
        $this->assertStringContainsString("Schema::create('reward_deliveries'", $migration);
        $this->assertStringContainsString("Schema::create('reward_delivery_items'", $migration);
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
