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
        $this->assertStringContainsString("\$fromVersion = '0.29.0'", $applyScript);
        $this->assertStringContainsString('app\Services\GameWorld\GameStatistics.php', $applyScript);
        $this->assertStringContainsString('app\Services\Rewards\DatabaseGameRewardQueueGateway.php', $applyScript);
        $this->assertStringContainsString('themes\default\views\statistics\index.blade.php', $applyScript);
        $this->assertStringContainsString('themes\kaev-aurelia\views\statistics\index.blade.php', $applyScript);
        $this->assertStringContainsString('tests\Unit\MobiusClassNamesTest.php', $applyScript);
        $this->assertStringContainsString('tests\powershell\update-workflow.ps1', $applyScript);
        $this->assertStringContainsString('scripts\release-update-support.ps1', $applyScript);
        $this->assertStringNotContainsString('Remove-Item -LiteralPath $obsoleteApplyScript.FullName', $applyScript);
        $this->assertStringNotContainsString('update.ps1 failed with exit code $LASTEXITCODE', $applyScript);
    }

    public function test_update_script_verifies_source_preserves_env_and_stages_cleanup_before_tests(): void
    {
        $updateScript = $this->readReleaseFile('update.ps1');

        $this->assertStringContainsString("\$expectedFromVersion = '0.29.0'", $updateScript);
        $this->assertStringContainsString("\$expectedToVersion = '0.29.2'", $updateScript);
        $this->assertStringContainsString("\$legacyApplyScriptName = 'apply-0.29.0.ps1'", $updateScript);
        $this->assertStringContainsString("\$legacyApplySha256 = '58e1dea45db84678133335c2c9b54bb6dbfd2128a98ef15a8e25cd22498f2a7e'", $updateScript);
        $this->assertStringContainsString('Get-KaevCmsInstalledVersion', $updateScript);
        $this->assertStringContainsString('-ExpectedToVersion $expectedToVersion', $updateScript);
        $this->assertStringContainsString('legacyApplySha256', $updateScript);
        $this->assertStringContainsString('Write-KaevCmsPendingUpdateMarker', $updateScript);
        $this->assertStringContainsString('Convert-KaevCmsSupersededPendingUpdateMarker', $updateScript);
        $this->assertStringContainsString("\$supersededPendingTargets = @('0.29.1')", $updateScript);
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
        $this->assertStringContainsString("'integrations\\reward-queue\\remove-legacy-bridge.sql'", $updateScript);

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

        $queueSql = $this->readReleaseFile('integrations/reward-queue/install.sql');
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `kaev_reward_queue`', $queueSql);
        $this->assertStringContainsString('`request_uuid` CHAR(36)', $queueSql);
        $this->assertStringContainsString('`item_id` BIGINT UNSIGNED', $queueSql);
        $this->assertStringContainsString('`amount` BIGINT UNSIGNED', $queueSql);

        $queueGateway = $this->readReleaseFile('app/Services/Rewards/DatabaseGameRewardQueueGateway.php');
        $this->assertStringContainsString("private const TABLE = 'kaev_reward_queue'", $queueGateway);
        $this->assertStringContainsString('reward_queue_payload_conflict', $queueGateway);
        $this->assertStringNotContainsString("table('items')", $queueGateway);

        $this->assertDirectoryDoesNotExist(base_path('integrations/mobius-interlude/reward-bridge'));
        $this->assertFileDoesNotExist(app_path('Jobs/ProcessRewardDelivery.php'));
        $this->assertFileDoesNotExist(app_path('Jobs/ConfirmRewardDelivery.php'));

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
        $this->assertStringContainsString('Kaev Aurelia Account 1.1.2', $aureliaCss);
        $this->assertStringContainsString('.promo-activation-surface {', $aureliaCss);
        $this->assertStringContainsString('.reward-history-main p img {', $aureliaCss);

        $aureliaNavigation = $this->readReleaseFile('account-themes/kaev-aurelia/views/partials/navigation.blade.php');
        $this->assertStringContainsString('wire:current="active"', $aureliaNavigation);
        $this->assertStringNotContainsString("request()->routeIs('modules.'", $aureliaNavigation);

        $aureliaInventory = $this->readReleaseFile('account-themes/kaev-aurelia/views/web-inventory/index.blade.php');
        $this->assertStringContainsString('account-surface reward-inventory-shell', $aureliaInventory);
    }

    public function test_web_inventory_release_artifacts_are_shipped(): void
    {
        $this->assertFileExists(app_path('Contracts/GameRewardQueueGateway.php'));
        $this->assertFileExists(app_path('Services/Rewards/DatabaseGameRewardQueueGateway.php'));
        $this->assertFileExists(app_path('Support/Rewards/RewardQueuePayload.php'));
        $this->assertFileExists(app_path('Models/RewardInventoryGrant.php'));
        $this->assertFileExists(app_path('Models/RewardInventoryItem.php'));
        $this->assertFileExists(app_path('Models/RewardDelivery.php'));
        $this->assertFileExists(app_path('Services/Rewards/RewardInventoryService.php'));
        $this->assertFileExists(app_path('Services/Rewards/RewardTransferService.php'));
        $this->assertFileExists(database_path('migrations/2026_07_21_000100_create_reward_inventory_tables.php'));
        $this->assertFileExists(base_path('docs/WEB_INVENTORY.md'));
        $this->assertFileExists(base_path('integrations/reward-queue/install.sql'));
        $this->assertFileExists(base_path('integrations/reward-queue/consumer-template.sql'));
        $this->assertFileExists(base_path('integrations/reward-queue/pending.sql'));
        $this->assertFileDoesNotExist(base_path('integrations/reward-queue/remove-legacy-bridge.sql'));
        $this->assertFileExists(base_path('account-themes/kaev-aurelia/views/web-inventory/index.blade.php'));
        $this->assertFileExists(base_path('account-themes/luxury/views/web-inventory/index.blade.php'));
        $this->assertFileExists(resource_path('views/admin/rewards/index.blade.php'));

        $contract = $this->readReleaseFile('app/Contracts/GameWorldDriver.php');
        $this->assertStringNotContainsString('rewardDeliveryCapabilities', $contract);
        $this->assertStringNotContainsString('deliverRewards', $contract);
        $this->assertStringNotContainsString('rewardDeliveryStatus', $contract);

        $queueContract = $this->readReleaseFile('app/Contracts/GameRewardQueueGateway.php');
        $this->assertStringContainsString('capabilities', $queueContract);
        $this->assertStringContainsString('enqueue', $queueContract);

        $mobiusDriver = $this->readReleaseFile('app/Services/GameWorld/MobiusGameWorldDriver.php');
        $this->assertStringNotContainsString("table('items')", $mobiusDriver);
        $this->assertStringNotContainsString('reward', strtolower($mobiusDriver));
        $this->assertStringContainsString('$profile->reputationColumn', $mobiusDriver);
        $this->assertFileExists(app_path('Services/GameWorld/MobiusGameSchemaInspector.php'));

        $mobiusAdapter = $this->readReleaseFile('app/Services/GameServer/MobiusGameServerAdapter.php');
        $serverMonitor = $this->readReleaseFile('app/Services/Servers/ServerMonitor.php');
        $this->assertStringNotContainsString("'message' => \$exception->getMessage()", $mobiusAdapter);
        $this->assertStringNotContainsString("'message' => \$exception->getMessage()", $serverMonitor);
        $this->assertFileExists(app_path('Services/GameWorld/MobiusGameSchemaProfile.php'));
        $this->assertFileExists(app_path('Services/GameAccounts/MobiusClassNames.php'));
        $this->assertFileDoesNotExist(app_path('Services/GameWorld/MobiusInterludeGameWorldDriver.php'));
        $this->assertFileDoesNotExist(app_path('Services/GameAccounts/InterludeClassNames.php'));
        $this->assertFileDoesNotExist(app_path('Services/GameWorld/InterludeCharacterLabels.php'));

        $migration = $this->readReleaseFile('database/migrations/2026_07_21_000100_create_reward_inventory_tables.php');
        $this->assertStringContainsString("Schema::create('reward_inventory_grants'", $migration);
        $this->assertStringContainsString("Schema::create('reward_inventory_items'", $migration);
        $this->assertStringContainsString("Schema::create('reward_deliveries'", $migration);
        $this->assertStringContainsString("Schema::create('reward_delivery_items'", $migration);
        $this->assertStringContainsString("\$table->timestamp('transferred_at')->nullable()", $migration);
        $this->assertStringContainsString("\$table->timestamp('queued_at')->nullable()", $migration);
    }

    public function test_promo_code_and_game_asset_release_artifacts_are_shipped(): void
    {
        $this->assertFileExists(app_path('Services/GameAssets/GameAssetUrlResolver.php'));
        $this->assertFileExists(app_path('Support/Modules/ModuleNavigationRegistry.php'));
        $this->assertFileExists(app_path('Support/Modules/ModuleGameServerDependencyRegistry.php'));
        $this->assertFileExists(base_path('modules/promo-codes/module.json'));
        $this->assertFileExists(base_path('modules/promo-codes/bootstrap.php'));
        $this->assertFileExists(base_path('modules/promo-codes/database/migrations/2026_07_21_100000_create_module_promo_codes_table.php'));
        $this->assertFileExists(base_path('modules/promo-codes/database/migrations/2026_07_21_100100_create_module_promo_code_rewards_table.php'));
        $this->assertFileExists(base_path('modules/promo-codes/database/migrations/2026_07_21_100200_create_module_promo_code_activations_table.php'));
        $this->assertFileExists(base_path('modules/promo-codes/database/migrations/2026_07_21_100300_add_deleted_at_to_module_promo_codes_table.php'));
        $this->assertFileExists(base_path('modules/promo-codes/src/Services/PromoCodeActivationService.php'));
        $this->assertFileExists(base_path('docs/PROMO_CODES.md'));
        $this->assertFileExists(base_path('docs/GAME_ASSETS.md'));
        $this->assertFileExists(public_path('assets/admin/js/promo-codes.js'));
        $this->assertFileExists(public_path('assets/admin/css/app.css'));

        $manifest = json_decode(
            $this->readReleaseFile('modules/promo-codes/module.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertSame('promo-codes', $manifest['id']);
        $this->assertSame('1.0.2', $manifest['version']);
        $this->assertSame('0.26.0', $manifest['cms_min']);
        $this->assertSame('database/migrations', $manifest['migrations']);

        $activationService = $this->readReleaseFile('modules/promo-codes/src/Services/PromoCodeActivationService.php');
        $this->assertStringContainsString('lockForUpdate()', $activationService);
        $this->assertStringContainsString("grantKey: 'promo-code.activation.'.\$activation->id", $activationService);
        $this->assertStringContainsString('RewardInventoryService', $activationService);
        $this->assertStringNotContainsString("table('items')", $activationService);

        $promoCodeModel = $this->readReleaseFile('modules/promo-codes/src/Models/PromoCode.php');
        $this->assertStringContainsString('use SoftDeletes;', $promoCodeModel);

        $promoScript = $this->readReleaseFile('public/assets/admin/js/promo-codes.js');
        $this->assertStringContainsString('data-promo-reward-add', $promoScript);
        $this->assertStringContainsString('data-promo-delete-form', $promoScript);

        $adminStyles = $this->readReleaseFile('public/assets/admin/css/app.css');
        $this->assertStringContainsString('.promo-reward-row', $adminStyles);

        $resolver = $this->readReleaseFile('app/Services/GameAssets/GameAssetUrlResolver.php');
        $this->assertStringContainsString("'items'", $resolver);
        $this->assertStringContainsString("'characters'", $resolver);
        $this->assertStringContainsString("'webp', 'png', 'jpg', 'jpeg'", $resolver);
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
