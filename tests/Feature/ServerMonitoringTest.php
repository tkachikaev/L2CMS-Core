<?php

namespace Tests\Feature;

use App\Contracts\GameServerOnlineCounter;
use App\Contracts\ServicePortProbe;
use App\Models\Admin;
use App\Models\GameServer;
use App\Models\LoginServer;
use App\Services\Servers\ServerMonitor;
use App\Services\Servers\ServerMonitorCoordinator;
use App\Services\Servers\ServerMonitorSettings;
use App\Services\Servers\ServerStatusOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithServerFixtures;
use Tests\Fakes\FakeGameServerOnlineCounter;
use Tests\Fakes\FakeServicePortProbe;
use Tests\TestCase;

class ServerMonitoringTest extends TestCase
{
    use InteractsWithServerFixtures, RefreshDatabase;

    public function test_monitor_records_live_services_and_online_players(): void
    {
        [$loginServer, $gameServer] = $this->servers();
        $ports = new FakeServicePortProbe;
        $ports->responses = [
            'login.example.test:2106' => true,
            'game.example.test:7777' => true,
        ];
        $online = new FakeGameServerOnlineCounter;
        $online->counts[$gameServer->id] = 73;
        $this->app->instance(ServicePortProbe::class, $ports);
        $this->app->instance(GameServerOnlineCounter::class, $online);

        app(ServerMonitor::class)->run();

        $this->assertDatabaseHas('login_servers', [
            'id' => $loginServer->id,
            'monitor_status' => 'online',
            'monitor_failures' => 0,
        ]);
        $this->assertDatabaseHas('game_servers', [
            'id' => $gameServer->id,
            'monitor_status' => 'online',
            'monitor_failures' => 0,
            'online_players' => 73,
        ]);
        $this->assertNotNull($loginServer->fresh()?->monitor_checked_at);
        $this->assertNotNull($gameServer->fresh()?->online_checked_at);
    }

    public function test_service_becomes_offline_only_after_three_consecutive_failures(): void
    {
        [$loginServer] = $this->servers();
        $ports = new FakeServicePortProbe;
        $ports->responses = [
            'login.example.test:2106' => [false, false, false],
            'game.example.test:7777' => [false, false, false],
        ];
        $this->app->instance(ServicePortProbe::class, $ports);
        $this->app->instance(GameServerOnlineCounter::class, new FakeGameServerOnlineCounter);
        $monitor = app(ServerMonitor::class);

        $monitor->run();
        $this->assertSame('unknown', $loginServer->fresh()?->monitor_status);
        $this->assertSame(1, $loginServer->fresh()?->monitor_failures);

        $monitor->run();
        $this->assertSame('unknown', $loginServer->fresh()?->monitor_status);
        $this->assertSame(2, $loginServer->fresh()?->monitor_failures);

        $monitor->run();
        $this->assertSame('offline', $loginServer->fresh()?->monitor_status);
        $this->assertSame(3, $loginServer->fresh()?->monitor_failures);
    }

    public function test_dashboard_and_public_home_show_compact_real_server_statuses(): void
    {
        $this->seed();
        GameServer::query()->delete();
        LoginServer::query()->delete();
        [, $gameServer] = $this->servers();
        $loginServer = $gameServer->loginServer;
        $this->assertInstanceOf(LoginServer::class, $loginServer);
        $loginServer->update([
            'monitor_status' => 'online',
            'monitor_failures' => 0,
            'monitor_checked_at' => now(),
            'monitor_last_online_at' => now(),
        ]);
        $gameServer->update([
            'monitor_status' => 'online',
            'monitor_failures' => 0,
            'monitor_checked_at' => now(),
            'monitor_last_online_at' => now(),
            'online_players' => 37,
            'online_checked_at' => now(),
        ]);
        $admin = Admin::factory()->create([
            'name' => 'Monitor Admin',
            'email' => 'monitor@example.com',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Всего онлайн')
            ->assertSee('37')
            ->assertSee('Interlude')
            ->assertSee('Основной LoginServer')
            ->assertSee('Работает');

        $this->get('/')
            ->assertOk()
            ->assertSee('Interlude')
            ->assertSee('В игре')
            ->assertSee('Онлайн: 37')
            ->assertDontSee('/ 5 000')
            ->assertDontSee('class="progress"', false);
    }

    public function test_admin_shows_each_process_state_while_public_status_requires_login_and_game_servers(): void
    {
        $this->seed();
        GameServer::query()->delete();
        LoginServer::query()->delete();
        [$loginServer, $gameServer] = $this->servers();
        $loginServer->update([
            'monitor_status' => 'offline',
            'monitor_failures' => 3,
            'monitor_checked_at' => now(),
        ]);
        $gameServer->update([
            'monitor_status' => 'online',
            'monitor_failures' => 0,
            'monitor_checked_at' => now(),
            'monitor_last_online_at' => now(),
            'online_players' => 12,
            'online_checked_at' => now(),
        ]);
        $admin = Admin::factory()->create([
            'name' => 'Monitor Admin',
            'email' => 'monitor-state@example.com',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Interlude')
            ->assertSee('12 онлайн')
            ->assertSee('Основной LoginServer');

        $this->get('/')
            ->assertOk()
            ->assertSee('Interlude')
            ->assertSee('Недоступно')
            ->assertSee('Онлайн: 12');
    }

    public function test_public_request_refreshes_stale_monitoring_without_system_scheduler(): void
    {
        $this->seed();
        GameServer::query()->delete();
        LoginServer::query()->delete();
        [, $gameServer] = $this->servers();
        $ports = new FakeServicePortProbe;
        $ports->responses = [
            'login.example.test:2106' => true,
            'game.example.test:7777' => true,
        ];
        $online = new FakeGameServerOnlineCounter;
        $online->counts[$gameServer->id] = 48;
        $this->app->instance(ServicePortProbe::class, $ports);
        $this->app->instance(GameServerOnlineCounter::class, $online);

        $this->postJson('/server-status/refresh')
            ->assertOk()
            ->assertJsonPath('refreshing', false)
            ->assertJsonPath('fresh', true)
            ->assertJsonPath('monitor.total_online', 48)
            ->assertJsonPath('monitor.game_servers.0.availability_state', 'online')
            ->assertJsonPath('monitor.game_servers.0.public_state_label', 'В игре')
            ->assertJsonPath('monitor.game_servers.0.public_online_label', 'Онлайн: 48');

        $this->assertSame('online', $gameServer->fresh()?->monitor_status);
        $this->assertSame(48, $gameServer->fresh()?->online_players);
    }

    public function test_home_marks_old_snapshot_for_automatic_refresh_and_does_not_show_old_offline_as_current(): void
    {
        $this->seed();
        GameServer::query()->delete();
        LoginServer::query()->delete();
        [$loginServer, $gameServer] = $this->servers();
        $old = now()->subHours(4);
        $loginServer->update([
            'monitor_status' => 'offline',
            'monitor_failures' => 3,
            'monitor_checked_at' => $old,
        ]);
        $gameServer->update([
            'monitor_status' => 'offline',
            'monitor_failures' => 3,
            'monitor_checked_at' => $old,
            'online_players' => 0,
            'online_checked_at' => $old,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-auto-refresh="1"', false)
            ->assertSee('Статус уточняется')
            ->assertDontSee('Недоступно');
    }

    public function test_fresh_snapshot_does_not_trigger_another_monitoring_run(): void
    {
        $this->seed();
        GameServer::query()->delete();
        LoginServer::query()->delete();
        [$loginServer, $gameServer] = $this->servers();
        $loginServer->update([
            'monitor_status' => 'online',
            'monitor_failures' => 0,
            'monitor_checked_at' => now(),
        ]);
        $gameServer->update([
            'monitor_status' => 'online',
            'monitor_failures' => 0,
            'monitor_checked_at' => now(),
            'online_players' => 17,
            'online_checked_at' => now(),
        ]);
        $ports = new FakeServicePortProbe;
        $ports->default = false;
        $this->app->instance(ServicePortProbe::class, $ports);
        $this->app->instance(GameServerOnlineCounter::class, new FakeGameServerOnlineCounter);

        $this->postJson('/server-status/refresh')
            ->assertOk()
            ->assertJsonPath('refreshing', false)
            ->assertJsonPath('fresh', true)
            ->assertJsonPath('monitor.total_online', 17)
            ->assertJsonPath('monitor.game_servers.0.availability_state', 'online');

        $this->assertSame('online', $gameServer->fresh()?->monitor_status);
        $this->assertSame(0, $gameServer->fresh()?->monitor_failures);
    }

    public function test_saved_refresh_interval_controls_when_monitoring_is_due(): void
    {
        config()->set('cms.server_monitor.refresh_interval_seconds', 60);
        [$loginServer, $gameServer] = $this->servers();
        $checkedAt = now()->subSeconds(45);
        $loginServer->update(['monitor_checked_at' => $checkedAt]);
        $gameServer->update(['monitor_checked_at' => $checkedAt]);

        $coordinator = app(ServerMonitorCoordinator::class);
        $this->assertFalse($coordinator->isDue());

        app(ServerMonitorSettings::class)->update(30);

        $this->assertSame(30, config('cms.server_monitor.refresh_interval_seconds'));
        $this->assertTrue($coordinator->isDue());
    }

    public function test_long_refresh_interval_does_not_make_snapshot_stale_before_next_check(): void
    {
        [$loginServer, $gameServer] = $this->servers();
        app(ServerMonitorSettings::class)->update(300);
        $this->assertSame(300, app(ServerMonitorSettings::class)->refreshIntervalSeconds());
        $checkedAt = now()->subSeconds(240);
        $loginServer->update([
            'monitor_status' => 'online',
            'monitor_checked_at' => $checkedAt,
        ]);
        $gameServer->update([
            'monitor_status' => 'online',
            'monitor_checked_at' => $checkedAt,
            'online_players' => 42,
            'online_checked_at' => $checkedAt,
        ]);

        $overview = app(ServerStatusOverview::class)->get();

        $this->assertSame('online', $overview['game_servers'][0]['state']);
        $this->assertSame('online', $overview['game_servers'][0]['availability_state']);
        $this->assertSame(42, $overview['game_servers'][0]['players']);
        $this->assertFalse(app(ServerMonitorCoordinator::class)->isDue());
    }

    public function test_monitor_command_respects_saved_interval_unless_forced(): void
    {
        [$loginServer, $gameServer] = $this->servers();
        app(ServerMonitorSettings::class)->update(300);
        $this->assertSame(300, app(ServerMonitorSettings::class)->refreshIntervalSeconds());
        $loginServer->update([
            'monitor_status' => 'online',
            'monitor_failures' => 0,
            'monitor_checked_at' => now(),
        ]);
        $gameServer->update([
            'monitor_status' => 'online',
            'monitor_failures' => 0,
            'monitor_checked_at' => now(),
        ]);
        $ports = new FakeServicePortProbe;
        $ports->default = false;
        $this->app->instance(ServicePortProbe::class, $ports);
        $this->app->instance(GameServerOnlineCounter::class, new FakeGameServerOnlineCounter);

        $this->assertSame(0, Artisan::call('l2forge:servers-monitor'));
        $this->assertStringContainsString('still fresh', Artisan::output());
        $this->assertSame(0, $loginServer->fresh()?->monitor_failures);

        $this->assertSame(0, Artisan::call('l2forge:servers-monitor', ['--force' => true]));
        $this->assertSame(1, $loginServer->fresh()?->monitor_failures);
        $this->assertSame(1, $gameServer->fresh()?->monitor_failures);
    }

    /** @return array{LoginServer,GameServer} */
    private function servers(): array
    {
        return $this->freshMobiusServerPair(
            [
                'name' => 'Основной LoginServer',
                'database_host' => 'db.example.test',
                'database_name' => 'login',
                'database_username' => 'l2',
                'database_charset' => 'utf8mb4',
            ],
            [
                'name' => 'Interlude',
                'rates' => 'x1',
                'sort_order' => 0,
            ],
        );
    }
}
