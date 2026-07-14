<?php

namespace App\Providers;

use App\Contracts\GameServerAdapter;
use App\Services\GameServer\MobiusGameServerAdapter;
use App\Services\GameServer\MockGameServerAdapter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class GameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameServerAdapter::class, function () {
            return match (config('game.adapter')) {
                'mock' => app(MockGameServerAdapter::class),
                'mobius' => app(MobiusGameServerAdapter::class),
                default => throw new InvalidArgumentException(
                    'Unsupported GAME_ADAPTER value: '.(string) config('game.adapter')
                ),
            };
        });
    }
}
