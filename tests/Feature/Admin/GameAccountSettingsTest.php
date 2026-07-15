<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Services\GameAccountSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GameAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_and_update_game_account_settings(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Main Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123'),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings/game-accounts')
            ->assertOk()
            ->assertSee('Игровые аккаунты')
            ->assertSee('Максимум аккаунтов на пользователя CMS');

        $this->actingAs($admin, 'admin')->put('/admin/settings/game-accounts', [
            'creation_enabled' => '1',
            'max_accounts' => 7,
            'login_min' => 5,
            'login_max' => 18,
            'login_lower' => '1',
            'login_upper' => '0',
            'login_digit' => '1',
            'password_min' => 10,
            'password_max' => 30,
            'password_lower' => '1',
            'password_upper' => '1',
            'password_digit' => '1',
        ])->assertRedirect(route('admin.settings.game-accounts'));

        $settings = app(GameAccountSettings::class)->values();
        $this->assertSame(7, $settings['max_accounts']);
        $this->assertSame(5, $settings['login_min']);
        $this->assertTrue($settings['login_lower']);
        $this->assertFalse($settings['login_upper']);
        $this->assertSame(10, $settings['password_min']);
    }
}
