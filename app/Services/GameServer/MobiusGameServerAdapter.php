<?php

namespace App\Services\GameServer;

use App\Contracts\GameServerAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MobiusGameServerAdapter implements GameServerAdapter
{
    public function status(): array
    {
        $portOnline = $this->portIsOpen(
            (string) config('game.server.host'),
            (int) config('game.server.port'),
        );

        try {
            $players = (int) DB::connection('game')
                ->table('characters')
                ->where('online', 1)
                ->count();
        } catch (Throwable $exception) {
            $this->reportGameDatabaseFailure('status', $exception);
            $players = 0;
        }

        return [
            'online' => $portOnline,
            'players' => $players,
            'max_players' => config('cms.server.max_online', 5000),
            'uptime' => null,
        ];
    }

    public function topCharacters(int $limit = 5): array
    {
        try {
            return DB::connection('game')
                ->table('characters')
                ->select(['char_name as name', 'classid as class', 'level'])
                ->where('accesslevel', 0)
                ->orderByDesc('level')
                ->orderByDesc('exp')
                ->limit(min(max($limit, 1), 100))
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (Throwable $exception) {
            $this->reportGameDatabaseFailure('topCharacters', $exception);

            return [];
        }
    }

    public function charactersForAccount(string $accountName): array
    {
        try {
            return DB::connection('game')
                ->table('characters')
                ->select(['char_name as name', 'classid as class', 'level', 'online'])
                ->where('account_name', $accountName)
                ->orderByDesc('level')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (Throwable $exception) {
            $this->reportGameDatabaseFailure('charactersForAccount', $exception);

            return [];
        }
    }

    public function accountExists(string $accountName): bool
    {
        try {
            return DB::connection('game')
                ->table('accounts')
                ->where('login', $accountName)
                ->exists();
        } catch (Throwable $exception) {
            $this->reportGameDatabaseFailure('accountExists', $exception);

            return false;
        }
    }

    private function portIsOpen(string $host, int $port): bool
    {
        $timeout = (float) config('game.server.timeout', 1.5);
        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, $timeout);

        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }

    private function reportGameDatabaseFailure(string $operation, Throwable $exception): void
    {
        Log::warning('Mobius database operation failed.', [
            'operation' => $operation,
            'exception' => $exception::class,
            'code' => (string) $exception->getCode(),
        ]);
    }
}
