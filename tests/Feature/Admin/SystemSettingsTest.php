<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Support\L2Forge;
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
            ->assertSee(L2Forge::version())
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

    public function test_version_is_read_from_the_root_version_file(): void
    {
        $versionPath = base_path('VERSION');

        $this->assertFileExists($versionPath);
        $this->assertSame(trim((string) file_get_contents($versionPath)), L2Forge::version());
        $this->assertMatchesRegularExpression(
            '/\A\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?\z/',
            L2Forge::version(),
        );
    }
}
