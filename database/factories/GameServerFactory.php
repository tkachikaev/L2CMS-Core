<?php

namespace Database\Factories;

use App\Models\GameServer;
use App\Models\LoginServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameServer> */
class GameServerFactory extends Factory
{
    protected $model = GameServer::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Interlude x10',
            'rates' => 'x10',
            'chronicle' => 'Interlude',
            'mode' => 'PvP',
            'sort_order' => 1,
            'login_server_id' => LoginServer::factory(),
            'driver' => 'l2j_mobius_ct0_interlude',
            'use_login_server_connection' => true,
            'database_host' => null,
            'database_port' => null,
            'database_name' => null,
            'database_username' => null,
            'database_password' => null,
            'database_charset' => null,
            'service_host' => 'game.example.test',
            'service_port' => 7777,
            'monitor_status' => 'unknown',
            'monitor_failures' => 0,
            'monitor_checked_at' => null,
            'monitor_last_online_at' => null,
            'online_players' => null,
            'online_checked_at' => null,
        ];
    }

    public function online(int $players = 0): static
    {
        return $this->state(fn (): array => [
            'monitor_status' => 'online',
            'monitor_failures' => 0,
            'monitor_checked_at' => now(),
            'monitor_last_online_at' => now(),
            'online_players' => max(0, $players),
            'online_checked_at' => now(),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (): array => [
            'monitor_status' => 'offline',
            'monitor_failures' => 3,
            'monitor_checked_at' => now(),
            'online_players' => null,
            'online_checked_at' => now(),
        ]);
    }

    public function stale(int $players = 0, string $status = 'online'): static
    {
        return $this->state(fn (): array => [
            'monitor_status' => $status,
            'monitor_failures' => $status === 'offline' ? 3 : 0,
            'monitor_checked_at' => now()->subHours(4),
            'monitor_last_online_at' => $status === 'online' ? now()->subHours(4) : null,
            'online_players' => max(0, $players),
            'online_checked_at' => now()->subHours(4),
        ]);
    }
}
