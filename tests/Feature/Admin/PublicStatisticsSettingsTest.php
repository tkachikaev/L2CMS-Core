<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\GameServerManager;
use App\Models\Admin;
use App\Models\GameServer;
use App\Services\GameServerSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicStatisticsSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_selected_public_statistics_sections(): void
    {
        $server = GameServer::query()->firstOrFail();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(GameServerManager::class)
            ->call('edit', $server->id)
            ->set('statisticsEnabled', true)
            ->set('statisticsLevelEnabled', true)
            ->set('statisticsPvpEnabled', true)
            ->set('statisticsPkEnabled', false)
            ->set('statisticsPlayTimeEnabled', true)
            ->set('statisticsHeroesEnabled', true)
            ->set('statisticsCastlesEnabled', true)
            ->set('statisticsLimit', '25')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Публичная статистика');

        $server->refresh();
        $this->assertTrue($server->statistics_enabled);
        $this->assertTrue($server->statistics_pvp_enabled);
        $this->assertFalse($server->statistics_pk_enabled);
        $this->assertSame(25, $server->statistics_limit);
    }

    public function test_unsupported_driver_cannot_publish_statistics(): void
    {
        $server = GameServer::query()->firstOrFail();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(GameServerManager::class)
            ->call('edit', $server->id)
            ->set('driver', 'rusacis')
            ->set('statisticsEnabled', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($server->fresh()->statistics_enabled);
    }

    public function test_unrelated_profile_update_preserves_statistics_settings(): void
    {
        $server = GameServer::query()->firstOrFail();
        $server->update([
            'statistics_enabled' => true,
            'statistics_pk_enabled' => false,
            'statistics_limit' => 25,
        ]);

        app(GameServerSettings::class)->update($server, [
            'name' => 'Renamed server',
        ]);

        $server->refresh();
        $this->assertTrue($server->statistics_enabled);
        $this->assertFalse($server->statistics_pk_enabled);
        $this->assertSame(25, $server->statistics_limit);
    }

    public function test_public_statistics_requires_at_least_one_section(): void
    {
        $server = GameServer::query()->firstOrFail();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(GameServerManager::class)
            ->call('edit', $server->id)
            ->set('statisticsEnabled', true)
            ->set('statisticsLevelEnabled', false)
            ->set('statisticsPvpEnabled', false)
            ->set('statisticsPkEnabled', false)
            ->set('statisticsPlayTimeEnabled', false)
            ->set('statisticsHeroesEnabled', false)
            ->set('statisticsCastlesEnabled', false)
            ->call('save')
            ->assertHasErrors(['statisticsEnabled']);
    }

    public function test_statistics_row_limit_is_validated(): void
    {
        $server = GameServer::query()->firstOrFail();
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(GameServerManager::class)
            ->call('edit', $server->id)
            ->set('statisticsEnabled', true)
            ->set('statisticsLimit', '101')
            ->call('save')
            ->assertHasErrors(['statisticsLimit']);
    }
}
