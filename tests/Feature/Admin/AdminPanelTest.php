<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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

        $response = $this->actingAs($admin, 'admin')->get('/admin/settings');

        $response
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
            ->assertSee('admin-menu-group-summary', false)
            ->assertSee('assets/admin/js/navigation.js', false)
            ->assertDontSee('Settings sections')
            ->assertDontSee('settings-tabs', false);

        $document = new DOMDocument;
        $this->assertTrue($document->loadHTML(
            $response->getContent(),
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        ));

        $xpath = new DOMXPath($document);
        $this->assertSame(
            1.0,
            $xpath->evaluate('count(//details[@data-admin-menu-group="site" and @open])'),
        );
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

    public function test_admin_sidebar_is_persisted_across_livewire_navigation(): void
    {
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));
        $panel = file_get_contents(resource_path('views/admin/layouts/panel.blade.php'));
        $navigation = file_get_contents(resource_path('views/admin/partials/navigation.blade.php'));

        $this->assertIsString($layout);
        $this->assertIsString($panel);
        $this->assertIsString($navigation);
        $this->assertStringContainsString('assets/admin/js/page-lifecycle.js', $layout);
        $this->assertStringContainsString('assets/admin/js/navigation.js', $layout);
        $this->assertStringContainsString('defer data-navigate-track data-navigate-once', $layout);
        $this->assertStringContainsString("@persist('admin-sidebar')", $panel);
        $this->assertStringContainsString('wire:navigate:scroll', $panel);
        $this->assertStringContainsString('data-admin-sidebar', $panel);
        $this->assertStringContainsString('wire:navigate.hover', $navigation);
        $this->assertStringContainsString('wire:current.exact="active"', $navigation);
        $this->assertStringNotContainsString("'active' => request()->routeIs", $navigation);
    }

    public function test_admin_navigation_stabilizes_the_shell_during_page_swaps(): void
    {
        $navigation = file_get_contents(public_path('assets/admin/js/navigation.js'));
        $styles = file_get_contents(public_path('assets/admin/css/app.css'));

        $this->assertIsString($navigation);
        $this->assertIsString($styles);
        $this->assertStringContainsString('livewire:navigate', $navigation);
        $this->assertStringContainsString('livewire:navigated', $navigation);
        $this->assertStringContainsString('admin-is-navigating', $navigation);
        $this->assertStringContainsString('scrollbar-gutter: stable', $styles);
        $this->assertStringContainsString('html.admin-is-navigating .admin-main', $styles);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $styles);
    }

    public function test_admin_scripts_use_one_livewire_page_lifecycle(): void
    {
        $lifecycle = file_get_contents(public_path('assets/admin/js/page-lifecycle.js'));
        $layout = file_get_contents(resource_path('views/admin/layouts/app.blade.php'));

        $this->assertIsString($lifecycle);
        $this->assertIsString($layout);
        $this->assertStringContainsString('registerPage(name, initialize)', $lifecycle);
        $this->assertStringContainsString('livewire:navigating', $lifecycle);
        $this->assertStringContainsString('livewire:navigated', $lifecycle);
        $this->assertStringContainsString('cleanupAll()', $lifecycle);
        $this->assertStringContainsString('page-lifecycle.js', $layout);

        $scripts = [
            'custom-mail.js',
            'localization.js',
            'mail-templates.js',
            'news-actions.js',
            'news-editor.js',
            'page-actions.js',
            'security.js',
            'server-monitor.js',
            'settings.js',
            'system.js',
            'two-factor.js',
            'users.js',
        ];

        foreach ($scripts as $script) {
            $contents = file_get_contents(public_path('assets/admin/js/'.$script));
            $this->assertIsString($contents);
            $this->assertStringContainsString('window.KaevCMSAdmin.registerPage', $contents, $script);
        }

        foreach (File::allFiles(resource_path('views/admin')) as $view) {
            $contents = $view->getContents();
            if (! str_contains($contents, 'assets/admin/js/')) {
                continue;
            }

            foreach (preg_split('/\R/', $contents) ?: [] as $line) {
                if (str_contains($line, 'assets/admin/js/') && str_contains($line, '<script src=')) {
                    $this->assertStringContainsString('data-navigate-once', $line, $view->getPathname());
                }
            }
        }
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
