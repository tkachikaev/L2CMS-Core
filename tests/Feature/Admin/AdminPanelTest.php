<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_root_is_the_main_panel_entry_point(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Main Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Панель управления')
            ->assertSee('Темы')
            ->assertSee('Новости')
            ->assertSee('Страницы')
            ->assertSee('Журнал действий')
            ->assertSee('class="admin-account-avatar" aria-hidden="true"><span>M</span>', false)
            ->assertSee('assets/admin/css/app.css');

        $adminCss = file_get_contents(public_path('assets/admin/css/app.css'));
        $this->assertIsString($adminCss);
        $this->assertStringContainsString(
            '.admin-account-avatar > span { display: grid; place-items: center; width: 100%; height: 100%; line-height: 1; transform: translateY(1px); }',
            $adminCss,
        );
    }

    public function test_settings_are_grouped_in_the_sidebar_without_global_tabs(): void
    {
        $admin = Admin::query()->create([
            'name' => 'English Admin',
            'email' => 'english-admin@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
            'locale' => 'en',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings')
            ->assertOk()
            ->assertSeeInOrder([
                'Content',
                'Site',
                'Main settings',
                'Languages',
                'Themes',
                'Servers',
                'Game servers',
                'Login Servers',
                'Game accounts',
                'Users',
                'Registration',
                'System',
                'Mail',
                'Security',
                'System information',
                'Administrators',
                'Audit log',
                'Modules',
            ])
            ->assertSee('data-admin-menu-group="site"', false)
            ->assertSee('class="admin-menu-group active"', false)
            ->assertSee('admin-menu-group-summary', false)
            ->assertSee('assets/admin/js/navigation.js', false)
            ->assertDontSee('Settings sections')
            ->assertDontSee('settings-tabs', false);
    }

    public function test_admin_navigation_uses_livewire_without_full_page_reload(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Navigation Admin',
            'email' => 'navigation-admin@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')->get('/admin');

        $response
            ->assertOk()
            ->assertSee('wire:navigate', false)
            ->assertSee('data-navigate-track', false)
            ->assertSee('livewire.js?id=', false)
            ->assertSee('data-navigate-once="true"', false);

        $this->assertGreaterThanOrEqual(16, substr_count($response->getContent(), 'wire:navigate'));
    }

    public function test_admin_scripts_are_safe_for_livewire_page_navigation(): void
    {
        $scripts = [
            'localization.js',
            'news-actions.js',
            'news-editor.js',
            'page-actions.js',
            'security.js',
            'server-monitor.js',
            'settings.js',
            'system.js',
        ];

        foreach ($scripts as $script) {
            $contents = file_get_contents(public_path('assets/admin/js/'.$script));
            $this->assertIsString($contents);
            $this->assertStringNotContainsString('DOMContentLoaded', $contents, $script);
        }

        $monitor = file_get_contents(public_path('assets/admin/js/server-monitor.js'));
        $settings = file_get_contents(public_path('assets/admin/js/settings.js'));

        $this->assertIsString($monitor);
        $this->assertIsString($settings);
        $this->assertStringContainsString('livewire:navigating', $monitor);
        $this->assertStringContainsString('AbortController', $monitor);
        $this->assertStringContainsString('livewire:navigating', $settings);
    }

    public function test_old_dashboard_address_redirects_to_admin_root(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Main Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/dashboard')
            ->assertRedirect('/admin');
    }
}
