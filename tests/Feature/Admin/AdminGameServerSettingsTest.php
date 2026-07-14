<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\GameServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminGameServerSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_game_servers(): void
    {
        $server = GameServer::query()->firstOrFail();

        $this->get('/admin/settings/game-server')
            ->assertRedirect(route('admin.login'));

        $this->post('/admin/settings/game-server', [
            'server_name' => 'Private server',
        ])->assertRedirect(route('admin.login'));

        $this->put('/admin/settings/game-server/'.$server->id, [
            'server_name' => 'Private server',
        ])->assertRedirect(route('admin.login'));

        $this->delete('/admin/settings/game-server/'.$server->id)
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_open_game_server_settings(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings/game-server')
            ->assertOk()
            ->assertSee('Игровых серверов')
            ->assertSee('Добавить игровой сервер')
            ->assertSee('Имя сервера')
            ->assertSee('Рейты сервера')
            ->assertSee('Хроники')
            ->assertSee('Режим')
            ->assertSee('Подключение к базе GameServer')
            ->assertSee('Сначала создайте LoginServer');
    }

    public function test_admin_can_update_server_and_leave_rates_and_chronicle_empty(): void
    {
        $admin = $this->createAdmin();
        $server = GameServer::query()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.game-server.update', $server), [
                'form_context' => 'server-'.$server->id,
                'server_name' => 'L2 Minimal',
                'server_rates' => '',
                'server_chronicle' => '',
                'server_mode' => 'None',
                'database_host' => 'should-not-be-stored',
                'database_password' => 'should-not-be-stored',
            ])
            ->assertRedirect(route('admin.settings.game-server'))
            ->assertSessionHas('status', 'Настройки игрового сервера сохранены.');

        $this->assertDatabaseHas('game_servers', [
            'id' => $server->id,
            'name' => 'L2 Minimal',
            'rates' => null,
            'chronicle' => null,
            'mode' => 'None',
        ]);
        $this->assertDatabaseMissing('cms_settings', ['value' => 'should-not-be-stored']);

        $this->get('/')
            ->assertOk()
            ->assertSee('L2 Minimal')
            ->assertDontSee('<dt>Версия</dt>', false)
            ->assertDontSee('<dt>Хроники</dt>', false)
            ->assertDontSee('<dt>Рейты</dt>', false)
            ->assertDontSee('<dt>Режим</dt>', false);
    }

    public function test_admin_can_add_multiple_servers_and_theme_renders_each_one(): void
    {
        $admin = $this->createAdmin();
        $first = GameServer::query()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.game-server.update', $first), [
                'server_name' => 'L2Forge x1',
                'server_rates' => 'x1',
                'server_chronicle' => 'High Five',
                'server_mode' => 'Craft',
            ])
            ->assertRedirect(route('admin.settings.game-server'));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.game-server.store'), [
                'form_context' => 'create',
                'server_name' => 'L2Forge x100',
                'server_rates' => 'x100',
                'server_chronicle' => 'Interlude',
                'server_mode' => 'PvP',
            ])
            ->assertRedirect(route('admin.settings.game-server'))
            ->assertSessionHas('status', 'Игровой сервер добавлен.');

        $this->assertDatabaseCount('game_servers', 2);
        $this->assertDatabaseHas('game_servers', [
            'name' => 'L2Forge x100',
            'rates' => 'x100',
            'chronicle' => 'Interlude',
            'mode' => 'PvP',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Игровые серверы')
            ->assertSee('L2Forge x1')
            ->assertSee('L2Forge x100')
            ->assertSee('<dt>Хроники</dt>', false)
            ->assertDontSee('<dt>Версия</dt>', false);

        $this->get('/about')
            ->assertOk()
            ->assertSee('L2Forge x1')
            ->assertSee('L2Forge x100')
            ->assertSee('High Five')
            ->assertSee('Interlude');
    }

    public function test_admin_can_delete_a_server_including_the_last_one(): void
    {
        $admin = $this->createAdmin();
        $server = GameServer::query()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.settings.game-server.destroy', $server))
            ->assertRedirect(route('admin.settings.game-server'))
            ->assertSessionHas('status', 'Игровой сервер «'.$server->name.'» удалён.');

        $this->assertDatabaseCount('game_servers', 0);

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings/game-server')
            ->assertOk()
            ->assertSee('Игровые серверы не добавлены');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Статус сервера')
            ->assertDontSee('Игровые серверы');
    }

    public function test_only_server_name_is_required(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->from('/admin/settings/game-server')
            ->post('/admin/settings/game-server', [
                'form_context' => 'create',
                'server_name' => '   ',
                'server_rates' => '',
                'server_chronicle' => '',
                'server_mode' => '',
            ])
            ->assertRedirect('/admin/settings/game-server')
            ->assertSessionHasErrors(['server_name'])
            ->assertSessionDoesntHaveErrors([
                'server_rates',
                'server_chronicle',
                'server_mode',
            ]);
    }

    private function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Main Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);
    }
}
