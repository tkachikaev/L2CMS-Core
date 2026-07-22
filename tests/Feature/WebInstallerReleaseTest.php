<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebInstallerReleaseTest extends TestCase
{
    public function test_web_installer_and_hosting_files_are_shipped(): void
    {
        $this->assertFileExists(public_path('install/index.php'));
        $this->assertFileExists(public_path('.htaccess'));
        $this->assertFileExists(base_path('deployment/hosting/web-installer/installer.php'));
        $this->assertFileExists(base_path('deployment/hosting/README.md'));
        $this->assertFileExists(base_path('deployment/hosting/web-installer/tests/installer-regression.php'));

        $entry = file_get_contents(public_path('index.php'));
        $installer = file_get_contents(base_path('deployment/hosting/web-installer/installer.php'));

        $this->assertNotFalse($entry);
        $this->assertNotFalse($installer);
        $this->assertStringContainsString("if (! is_file(\$projectRoot.'/.env'))", $entry);
        $this->assertStringContainsString("header('Location: '.\$basePath.'/install/'", $entry);
        $this->assertStringContainsString('storage/app/installed.lock', $installer);
        $this->assertStringContainsString('session_set_cookie_params([', $installer);
        $this->assertStringContainsString("'httponly' => true", $installer);
        $this->assertStringContainsString("'samesite' => 'Lax'", $installer);
        $this->assertStringContainsString('Content-Security-Policy:', $installer);
        $this->assertStringContainsString("header_remove('X-Powered-By')", $installer);
        $this->assertStringContainsString('LOCK_EX | LOCK_NB', $installer);
        $this->assertStringContainsString('CREATE TABLE {$quoted}', $installer);
        $this->assertStringContainsString('DROP TABLE {$quoted}', $installer);
        $this->assertStringContainsString('buildEnvironmentContent', $installer);
        $this->assertStringContainsString('publicInstallerError', $installer);
        $this->assertStringContainsString('hash_equals($expected, $provided)', $installer);
        $this->assertStringContainsString("PDO::ATTR_EMULATE_PREPARES => false", $installer);
        $this->assertStringContainsString("field(\$text['db_password'], 'db_password', '', 'password'", $installer);
        $this->assertStringNotContainsString("field(\$text['db_password'], 'db_password', \$db['password']", $installer);
        $this->assertStringContainsString("callArtisanOrFail('migrate'", $installer);
        $this->assertStringContainsString('Hash::make($administrator[\'password\'])', $installer);
        $this->assertStringNotContainsString('shell_exec(', $installer);
        $this->assertStringNotContainsString('passthru(', $installer);
        $this->assertStringNotContainsString('proc_open(', $installer);
        $this->assertStringNotContainsString('system(', $installer);
    }

    public function test_windows_scripts_are_kept_in_one_deployment_directory(): void
    {
        foreach ([
            'setup.ps1',
            'serve.ps1',
            'doctor.ps1',
            'quality.ps1',
            'browser-setup.ps1',
            'browser-quality.ps1',
            'security-audit.ps1',
            'update.ps1',
            'apply-0.31.10.ps1',
        ] as $script) {
            $this->assertFileExists(base_path('deployment/windows/'.$script));
            $this->assertFileDoesNotExist(base_path($script));
        }

        $quality = file_get_contents(base_path('deployment/windows/quality.ps1'));
        $this->assertNotFalse($quality);
        $this->assertStringContainsString("Join-Path \$PSScriptRoot '..\\..'", $quality);
        $this->assertStringContainsString('tests\\update-workflow.ps1', $quality);
        $this->assertStringContainsString('deployment/hosting/web-installer/tests/installer-regression.php', $quality);
    }
}
