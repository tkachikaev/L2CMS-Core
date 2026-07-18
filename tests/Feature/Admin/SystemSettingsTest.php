<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Services\Servers\ServerMonitorSettings;
use App\Support\KaevCMS;
use App\Support\PasswordHashing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_information_is_available_only_to_administrators(): void
    {
        $this->get('/admin/settings/system')
            ->assertRedirect(route('admin.login'));

        $admin = Admin::query()->create([
            'name' => 'System Admin',
            'email' => 'system@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);

        config()->set('app.key', 'base64:THIS_MUST_NOT_BE_RENDERED_IN_SYSTEM_INFORMATION');

        $hashLabel = PasswordHashing::label();

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings/system')
            ->assertOk()
            ->assertSee('Состояние компонентов')
            ->assertSee('Расширения PHP')
            ->assertSee(KaevCMS::version())
            ->assertSee(PHP_VERSION)
            ->assertSee(app()->version())
            ->assertSee('Тип хеша')
            ->assertSee($hashLabel)
            ->assertDontSee('THIS_MUST_NOT_BE_RENDERED_IN_SYSTEM_INFORMATION');
    }

    public function test_system_information_explains_bcrypt_only_when_argon2id_is_unavailable(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Hash Admin',
            'email' => 'hash@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);

        config()->set('hashing.driver', 'bcrypt');

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/settings/system')
            ->assertOk()
            ->assertSee('Тип хеша')
            ->assertSee('bcrypt');

        if (PasswordHashing::argon2idSupported()) {
            $response->assertDontSee('Argon2id не поддерживается системой.');
        } else {
            $response->assertSee('Argon2id не поддерживается системой.');
        }
    }

    public function test_server_monitor_refresh_interval_can_be_saved_from_system_page(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Monitor Settings Admin',
            'email' => 'monitor-settings@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings/system')
            ->assertOk()
            ->assertSee('Мониторинг серверов')
            ->assertSee('Интервал обновления статуса')
            ->assertSee('Как часто CMS может повторно проверять доступность LoginServer и GameServer')
            ->assertSee('30 секунд')
            ->assertSee('1 минута')
            ->assertSee('2 минуты')
            ->assertSee('5 минут');

        $this->actingAs($admin, 'admin')
            ->put('/admin/settings/system/monitoring', [
                'refresh_interval_seconds' => 120,
            ])
            ->assertRedirect(route('admin.settings.system'))
            ->assertSessionHas('status', 'Настройки мониторинга серверов сохранены.');

        $this->assertDatabaseHas('cms_settings', [
            'key' => ServerMonitorSettings::KEY_REFRESH_INTERVAL_SECONDS,
            'value' => '120',
        ]);
        $this->assertSame(120, app(ServerMonitorSettings::class)->refreshIntervalSeconds());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'settings.server_monitor_updated',
            'result' => 'success',
        ]);
    }

    public function test_server_monitor_refresh_interval_accepts_only_safe_options(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Monitor Validation Admin',
            'email' => 'monitor-validation@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->from('/admin/settings/system')
            ->put('/admin/settings/system/monitoring', [
                'refresh_interval_seconds' => 15,
            ])
            ->assertRedirect('/admin/settings/system')
            ->assertSessionHasErrors('refresh_interval_seconds');

        $this->assertDatabaseMissing('cms_settings', [
            'key' => ServerMonitorSettings::KEY_REFRESH_INTERVAL_SECONDS,
        ]);
    }

    public function test_version_is_read_from_the_root_version_file(): void
    {
        $versionPath = base_path('VERSION');

        $this->assertFileExists($versionPath);
        $this->assertSame(trim((string) file_get_contents($versionPath)), KaevCMS::version());
        $this->assertMatchesRegularExpression(
            '/\A\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?\z/',
            KaevCMS::version(),
        );
    }
}
