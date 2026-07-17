<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrowserDependencyLockTest extends TestCase
{
    public function test_browser_dependency_lock_uses_the_public_npm_registry(): void
    {
        $lock = file_get_contents(base_path('package-lock.json'));

        $this->assertNotFalse($lock);
        $this->assertStringNotContainsString('applied-caas-gateway', $lock);
        $this->assertStringNotContainsString('.internal.api.openai.org', $lock);
        $this->assertStringContainsString('https://registry.npmjs.org/@playwright/test/', $lock);
        $this->assertStringContainsString('https://registry.npmjs.org/playwright-core/', $lock);
    }
}
