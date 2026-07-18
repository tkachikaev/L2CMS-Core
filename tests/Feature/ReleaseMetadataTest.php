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
        $this->assertStringContainsString('Write-Host "KaevCMS $cmsVersion update"', $updateScript);

        $applyScripts = glob(base_path('apply-*.ps1')) ?: [];
        sort($applyScripts);

        $this->assertCount(1, $applyScripts, 'A release must contain exactly one current apply script.');
        $this->assertSame("apply-{$version}.ps1", basename($applyScripts[0]));

        $applyScript = (string) file_get_contents($applyScripts[0]);
        $this->assertStringContainsString("if (\$cmsVersion -ne '{$version}')", $applyScript);
        $this->assertStringContainsString("Where-Object { \$_.Name -ne 'apply-{$version}.ps1' }", $applyScript);
    }

    public function test_update_script_cleans_obsolete_release_artifacts_before_tests(): void
    {
        $updateScript = $this->readReleaseFile('update.ps1');

        $this->assertStringContainsString('function Remove-ObsoleteReleaseArtifacts', $updateScript);
        $this->assertStringContainsString("Get-ChildItem -LiteralPath \$PSScriptRoot -Filter 'apply-*.ps1'", $updateScript);
        $this->assertStringContainsString("'preview'", $updateScript);
        $this->assertStringContainsString("'resources\\views\\admin\\settings\\placeholder.blade.php'", $updateScript);
        $this->assertStringContainsString('Remove-ObsoleteReleaseArtifacts -CurrentVersion $cmsVersion', $updateScript);

        $cleanupPosition = strpos($updateScript, 'Remove-ObsoleteReleaseArtifacts -CurrentVersion $cmsVersion');
        $testPosition = strpos($updateScript, 'php artisan test');

        $this->assertNotFalse($cleanupPosition);
        $this->assertNotFalse($testPosition);
        $this->assertLessThan($testPosition, $cleanupPosition);
    }

    public function test_obsolete_preview_and_settings_placeholder_are_not_shipped(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('preview'));
        $this->assertFileDoesNotExist(resource_path('views/admin/settings/placeholder.blade.php'));
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
