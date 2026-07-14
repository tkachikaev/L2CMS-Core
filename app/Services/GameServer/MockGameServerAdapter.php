<?php

namespace App\Services\GameServer;

use App\Contracts\GameServerAdapter;

final class MockGameServerAdapter implements GameServerAdapter
{
    public function status(): array
    {
        return ['online' => true, 'players' => 1254, 'max_players' => config('cms.server.max_online', 5000), 'uptime' => '15d 7h'];
    }

    public function topCharacters(int $limit = 5): array
    {
        return array_slice([
            ['name' => 'TheGreatPlayer', 'class' => 'Duelist', 'level' => 85],
            ['name' => 'DarkSoul', 'class' => 'Archmage', 'level' => 85],
            ['name' => 'QueenOfMoon', 'class' => 'Cardinal', 'level' => 85],
            ['name' => 'KamaelStyle', 'class' => 'Doombringer', 'level' => 84],
            ['name' => 'INeverDie', 'class' => 'Titan', 'level' => 84],
        ], 0, $limit);
    }

    public function charactersForAccount(string $accountName): array
    {
        return [];
    }

    public function accountExists(string $accountName): bool
    {
        return false;
    }
}
