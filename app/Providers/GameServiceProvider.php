<?php

namespace App\Providers;

use App\Contracts\ExternalDatabaseConnectionTester;
use App\Contracts\GameAccountGateway;
use App\Contracts\GameServerAdapter;
use App\Services\GameAccounts\ExternalGameAccountGateway;
use App\Services\GameServer\MobiusGameServerAdapter;
use App\Services\GameServer\MockGameServerAdapter;
use App\Services\Servers\MySqlExternalDatabaseConnectionTester;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class GameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExternalDatabaseConnectionTester::class, MySqlExternalDatabaseConnectionTester::class);
        $this->app->singleton(GameAccountGateway::class, ExternalGameAccountGateway::class);

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
