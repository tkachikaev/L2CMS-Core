<?php

namespace Tests\Feature\Modules;

use App\Auth\AdminRole;
use App\Models\Admin;
use App\Models\ModuleState;
use App\Support\Modules\ModuleManager;
use App\Support\Modules\ModuleRuntime;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class ModuleFoundationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $createdModules = [];

    protected function tearDown(): void
    {
        $files = new Filesystem;

        foreach ($this->createdModules as $module) {
            $files->deleteDirectory(base_path('modules/'.$module));
        }

        if (app()->bound(ModuleManager::class)) {
            app(ModuleManager::class)->refresh();
        }

        parent::tearDown();
    }

    public function test_manifest_is_strictly_validated_before_a_module_can_be_enabled(): void
    {
        $this->createModule('foundation-fixture', [
            'description' => 'Valid module fixture.',
            'cms_min' => '0.24.0',
            'cms_max' => '0.24.99',
        ]);

        $module = app(ModuleManager::class)->inspect('foundation-fixture');

        $this->assertTrue($module['valid'], implode(PHP_EOL, $module['errors']));
        $this->assertTrue($module['compatible'], implode(PHP_EOL, $module['errors']));
        $this->assertSame('disabled', $module['status']);
        $this->assertTrue($module['can_enable']);

        $this->createModule('unsafe-fixture', [
            'bootstrap' => '../bootstrap.php',
        ]);

        $unsafe = app(ModuleManager::class)->inspect('unsafe-fixture');

        $this->assertFalse($unsafe['valid']);
        $this->assertFalse($unsafe['can_enable']);
        $this->assertContains(
            __('The module :field file was not found or is unsafe.', ['field' => 'bootstrap']),
            $unsafe['errors'],
        );

        $this->createModule('malformed-fixture', [
            'name' => str_repeat('M', 121),
            'version' => ['not', 'text'],
            'description' => ['not', 'text'],
            'cms_min' => 24,
            'unexpected' => true,
        ]);

        $malformed = app(ModuleManager::class)->inspect('malformed-fixture');

        $this->assertFalse($malformed['valid']);
        $this->assertContains(
            __('The :field field exceeds the maximum length of :max characters.', ['field' => 'name', 'max' => 120]),
            $malformed['errors'],
        );
        $this->assertContains(
            __('The module version must use semantic versioning.'),
            $malformed['errors'],
        );
        $this->assertContains(
            __('The :field field must contain text.', ['field' => 'description']),
            $malformed['errors'],
        );
        $this->assertContains(
            __('The :field field must contain a valid CMS version.', ['field' => 'cms_min']),
            $malformed['errors'],
        );
        $this->assertContains(
            __('Unknown module.json field: :field.', ['field' => 'unexpected']),
            $malformed['errors'],
        );

        $this->createModule('future-fixture', ['cms_min' => '99.0.0']);
        $future = app(ModuleManager::class)->inspect('future-fixture');
        $this->assertTrue($future['valid']);
        $this->assertFalse($future['compatible']);
        $this->assertSame('incompatible', $future['status']);
        $this->assertFalse($future['can_enable']);

        $this->createModule('admin');
        $reserved = app(ModuleManager::class)->inspect('admin');
        $this->assertFalse($reserved['valid']);
        $this->assertContains(__('This module identifier is reserved by KaevCMS.'), $reserved['errors']);
    }

    public function test_owner_manages_modules_administrator_is_read_only_and_editor_is_denied(): void
    {
        $this->createModule('lifecycle-fixture', [
            'name' => 'Lifecycle Fixture',
        ]);

        $owner = $this->createAdmin(AdminRole::Owner, 'owner@example.test');
        $administrator = $this->createAdmin(AdminRole::Administrator, 'administrator@example.test');
        $editor = $this->createAdmin(AdminRole::Editor, 'editor@example.test');

        $this->actingAs($administrator, 'admin')
            ->get('/admin/modules')
            ->assertOk()
            ->assertSee('Lifecycle Fixture')
            ->assertSee(__('Read-only mode'));

        $this->actingAs($administrator, 'admin')
            ->post('/admin/modules/lifecycle-fixture/enable')
            ->assertForbidden();

        $this->actingAs($editor, 'admin')
            ->get('/admin/modules')
            ->assertForbidden();

        $this->actingAs($owner, 'admin')
            ->post('/admin/modules/lifecycle-fixture/enable')
            ->assertRedirect(route('admin.modules.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('cms_modules', [
            'id' => 'lifecycle-fixture',
            'version' => '1.0.0',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'category' => 'module',
            'action' => 'module.enabled',
            'target_name' => 'lifecycle-fixture',
        ]);

        $this->actingAs($owner, 'admin')
            ->delete('/admin/modules/lifecycle-fixture/disable')
            ->assertRedirect(route('admin.modules.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('cms_modules', [
            'id' => 'lifecycle-fixture',
            'enabled' => false,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'category' => 'module',
            'action' => 'module.disabled',
            'target_name' => 'lifecycle-fixture',
        ]);
    }

    public function test_enabled_module_registers_bootstrap_views_translations_and_scoped_routes(): void
    {
        $id = 'runtime-fixture';
        $root = $this->createModule($id, [
            'name' => 'Runtime Fixture',
            'namespace' => 'KaevCMS\\Modules\\RuntimeFixture\\',
            'autoload' => 'src',
            'bootstrap' => 'bootstrap.php',
            'views' => 'resources/views',
            'lang' => 'lang',
            'routes' => [
                'web' => 'routes/web.php',
                'admin' => 'routes/admin.php',
            ],
        ]);

        $files = new Filesystem;
        $files->ensureDirectoryExists($root.'/src');
        $files->ensureDirectoryExists($root.'/resources/views');
        $files->ensureDirectoryExists($root.'/lang/ru');
        $files->ensureDirectoryExists($root.'/routes');
        $files->put($root.'/src/RuntimeMarker.php', <<<'PHP'
<?php

namespace KaevCMS\Modules\RuntimeFixture;

final readonly class RuntimeMarker
{
    public function __construct(public string $moduleId) {}
}
PHP);
        $files->put($root.'/bootstrap.php', <<<'PHP'
<?php

use App\Support\Modules\ModuleContext;
use Illuminate\Contracts\Foundation\Application;
use KaevCMS\Modules\RuntimeFixture\RuntimeMarker;

return static function (Application $app, ModuleContext $module): void {
    $app->instance('runtime-fixture.marker', ['module_id' => (new RuntimeMarker($module->id))->moduleId]);
};
PHP);
        $files->put($root.'/resources/views/index.blade.php', <<<'BLADE'
<div data-runtime-module>{{ __('module-runtime-fixture::messages.ready') }}</div>
BLADE);
        $files->put($root.'/lang/ru/messages.php', <<<'PHP'
<?php

return ['ready' => 'Модуль загружен'];
PHP);
        $files->put($root.'/routes/web.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['module' => 'runtime-fixture']))->name('ping');
Route::get('/view', fn () => view('module-runtime-fixture::index'))->name('view');
PHP);
        $files->put($root.'/routes/admin.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/status', fn () => response('runtime-admin-ok'))->name('status');
PHP);

        $modules = app(ModuleManager::class);
        $modules->refresh();
        $modules->enable($id);
        app(ModuleRuntime::class)->bootModule($modules->inspect($id));

        $marker = app('runtime-fixture.marker');
        $this->assertIsArray($marker);
        $this->assertSame($id, $marker['module_id']);
        $this->get('/modules/runtime-fixture/ping')
            ->assertOk()
            ->assertJson(['module' => 'runtime-fixture']);
        $this->get('/modules/runtime-fixture/view')
            ->assertOk()
            ->assertSee('Модуль загружен')
            ->assertSee('data-runtime-module', false);

        $owner = $this->createAdmin(AdminRole::Owner, 'runtime-owner@example.test');
        $administrator = $this->createAdmin(AdminRole::Administrator, 'runtime-administrator@example.test');
        $editor = $this->createAdmin(AdminRole::Editor, 'runtime-editor@example.test');

        $this->actingAs($owner, 'admin')
            ->get('/admin/extensions/runtime-fixture/status')
            ->assertOk()
            ->assertSee('runtime-admin-ok');
        $this->actingAs($administrator, 'admin')
            ->get('/admin/extensions/runtime-fixture/status')
            ->assertOk();
        $this->actingAs($editor, 'admin')
            ->get('/admin/extensions/runtime-fixture/status')
            ->assertForbidden();

        ModuleState::query()->whereKey($id)->update([
            'last_error' => 'boot: RuntimeException',
            'last_error_at' => now(),
        ]);
        $modules->refresh();
        $this->get('/modules/runtime-fixture/ping')->assertStatus(503);

        ModuleState::query()->whereKey($id)->update([
            'last_error' => null,
            'last_error_at' => null,
        ]);
        $modules->refresh();
        $modules->disable($id);

        // Already-registered or stale cached routes must not execute after a
        // module is disabled, replaced, damaged or awaiting update approval.
        $this->get('/modules/runtime-fixture/ping')->assertNotFound();
        $this->actingAs($owner, 'admin')
            ->get('/admin/extensions/runtime-fixture/status')
            ->assertNotFound();
    }

    public function test_module_routes_are_excluded_from_core_route_cache_builds(): void
    {
        $id = 'cached-route-fixture';
        $root = $this->createModule($id, [
            'name' => 'Cached Route Fixture',
            'routes' => ['web' => 'routes/web.php'],
        ]);

        $files = new Filesystem;
        $files->ensureDirectoryExists($root.'/routes');
        $files->put($root.'/routes/web.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/cached', fn () => response('cached-module-route'))->name('cached');
PHP);

        $modules = app(ModuleManager::class);
        $modules->refresh();
        $module = $modules->enable($id);
        $routeCount = Route::getRoutes()->count();

        $application = Mockery::mock(Application::class);
        $application->shouldReceive('runningInConsole')->once()->andReturnTrue();
        $previousArguments = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'route:cache'];

        try {
            (new ModuleRuntime($application, $files))->bootModule($module);
        } finally {
            if ($previousArguments === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $previousArguments;
            }
        }

        $this->assertSame($routeCount, Route::getRoutes()->count());
        $this->assertFalse(Route::has('modules.cached-route-fixture.cached'));
    }

    public function test_broken_enabled_module_is_isolated_reported_and_retried_after_fix(): void
    {
        $id = 'broken-runtime-fixture';
        $root = $this->createModule($id, [
            'name' => 'Broken Runtime Fixture',
            'bootstrap' => 'bootstrap.php',
        ]);
        $files = new Filesystem;
        $files->put($root.'/bootstrap.php', "<?php\n\nreturn 'not-callable';\n");

        $modules = app(ModuleManager::class);
        $modules->refresh();
        $modules->enable($id);
        app(ModuleRuntime::class)->bootEnabled($modules);
        $modules->refresh();

        $broken = $modules->inspect($id);
        $this->assertSame('runtime_error', $broken['status']);
        $this->assertStringContainsString('boot: RuntimeException', (string) $broken['last_error']);
        $this->get('/')->assertOk();

        $files->put($root.'/bootstrap.php', <<<'PHP'
<?php

use App\Support\Modules\ModuleContext;
use Illuminate\Contracts\Foundation\Application;

return static function (Application $app, ModuleContext $module): void {
    $app->instance('broken-runtime-fixture.recovered', $module->id);
};
PHP);
        $modules->refresh();
        app(ModuleRuntime::class)->bootEnabled($modules);
        $this->assertFalse(app()->bound('broken-runtime-fixture.recovered'));

        ModuleState::query()->whereKey($id)->update([
            'last_error_at' => now()->subMinutes(2),
        ]);
        $modules->refresh();
        app(ModuleRuntime::class)->bootEnabled($modules);
        $modules->refresh();

        $recovered = $modules->inspect($id);
        $this->assertSame('enabled', $recovered['status']);
        $this->assertNull($recovered['last_error']);
        $this->assertSame($id, app('broken-runtime-fixture.recovered'));
    }

    public function test_changed_module_version_requires_owner_approval_before_runtime_loading(): void
    {
        $id = 'update-fixture';
        $root = $this->createModule($id, ['name' => 'Update Fixture']);
        $modules = app(ModuleManager::class);

        $modules->enable($id);
        $this->assertSame('enabled', $modules->inspect($id)['status']);

        $manifest = json_decode((new Filesystem)->get($root.'/module.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifest['version'] = '1.1.0';
        (new Filesystem)->put(
            $root.'/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
        $modules->refresh();

        $pending = $modules->inspect($id);
        $this->assertSame('update_pending', $pending['status']);
        $this->assertTrue($pending['update_available']);
        $this->assertTrue($pending['can_enable']);
        $this->assertSame([], $modules->enabled());

        $modules->enable($id);

        $approved = $modules->inspect($id);
        $this->assertSame('enabled', $approved['status']);
        $this->assertFalse($approved['update_available']);
        $this->assertSame('1.1.0', $approved['stored_version']);
    }

    public function test_enabled_state_with_missing_files_remains_visible_and_can_be_disabled(): void
    {
        ModuleState::query()->create([
            'id' => 'removed-fixture',
            'version' => '1.4.0',
            'enabled' => true,
            'enabled_at' => now(),
        ]);

        $module = app(ModuleManager::class)->inspect('removed-fixture');

        $this->assertSame('missing', $module['status']);
        $this->assertTrue($module['can_disable']);
        $this->assertFalse($module['can_enable']);

        app(ModuleManager::class)->disable('removed-fixture');

        $this->assertDatabaseHas('cms_modules', [
            'id' => 'removed-fixture',
            'enabled' => false,
        ]);
        $this->assertFalse(collect(app(ModuleManager::class)->installed())->contains('id', 'removed-fixture'));
    }

    /** @param array<string, mixed> $overrides */
    private function createModule(string $id, array $overrides = []): string
    {
        $root = base_path('modules/'.$id);
        $manifest = array_merge([
            'schema' => 1,
            'id' => $id,
            'name' => str($id)->replace('-', ' ')->headline()->toString(),
            'version' => '1.0.0',
            'author' => 'KaevCMS Tests',
            'description' => 'Test module.',
            'cms_min' => '0.24.0',
            'cms_max' => null,
        ], $overrides);

        $files = new Filesystem;
        $files->ensureDirectoryExists($root);
        $files->put(
            $root.'/module.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        $this->createdModules[] = $id;
        app(ModuleManager::class)->refresh();

        return $root;
    }

    private function createAdmin(AdminRole $role, string $email): Admin
    {
        return Admin::query()->create([
            'name' => $role->label(),
            'email' => $email,
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
            'role' => $role,
        ]);
    }
}
