<?php

namespace App\Services\Servers;

use App\Models\GameServer;
use App\Models\LoginServer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Throwable;

final class ServerStatusOverview
{
    public function __construct(private readonly ServerMonitorSettings $settings) {}

    /**
     * @return array{
     *     total_online:int,
     *     partial:bool,
     *     checked_at:CarbonInterface|null,
     *     game_servers:list<array{id:int,name:string,state:string,availability_state:string,players:int|null,checked_at:CarbonInterface|null}>,
     *     login_servers:list<array{id:int,name:string,state:string,checked_at:CarbonInterface|null}>
     * }
     */
    public function get(?string $locale = null): array
    {
        $loginServers = LoginServer::query()->orderBy('id')->get();
        $loginStates = $loginServers->mapWithKeys(
            fn (LoginServer $server): array => [$server->id => $this->state($server->monitor_status, $server->monitor_checked_at)],
        );
        $gameServers = GameServer::query()
            ->with(['translations', 'loginServer'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $games = $gameServers->map(function (GameServer $server) use ($locale, $loginStates): array {
            $state = $this->state($server->monitor_status, $server->monitor_checked_at);
            $loginState = $server->login_server_id !== null
                ? (string) $loginStates->get($server->login_server_id, 'unknown')
                : 'unknown';
            $players = $state === 'online' && $this->isFresh($server->online_checked_at, $this->onlineStaleSeconds())
                ? max(0, (int) $server->online_players)
                : null;

            return [
                'id' => (int) $server->id,
                'name' => $server->nameFor($locale),
                'state' => $state,
                'availability_state' => $this->combinedState($state, $loginState),
                'players' => $players,
                'checked_at' => $this->date($server->monitor_checked_at),
            ];
        })->values();

        $logins = $loginServers->map(fn (LoginServer $server): array => [
            'id' => (int) $server->id,
            'name' => $server->name,
            'state' => (string) $loginStates->get($server->id, 'unknown'),
            'checked_at' => $this->date($server->monitor_checked_at),
        ])->values();

        return [
            'total_online' => $games->sum(fn (array $server): int => $server['players'] ?? 0),
            'partial' => $games->contains(
                fn (array $server): bool => $server['state'] === 'unknown'
                    || ($server['state'] === 'online' && $server['players'] === null),
            ),
            'checked_at' => $this->latestCheckedAt(
                $games->pluck('checked_at')->values()->all(),
                $logins->pluck('checked_at')->values()->all(),
            ),
            'game_servers' => $games->all(),
            'login_servers' => $logins->all(),
        ];
    }

    private function state(?string $status, mixed $checkedAt): string
    {
        if (! $this->isFresh($checkedAt, $this->statusStaleSeconds())) {
            return 'unknown';
        }

        return in_array($status, ['online', 'offline'], true) ? $status : 'unknown';
    }

    private function combinedState(string $gameState, string $loginState): string
    {
        if ($gameState === 'offline' || $loginState === 'offline') {
            return 'offline';
        }

        if ($gameState === 'online' && $loginState === 'online') {
            return 'online';
        }

        return 'unknown';
    }

    private function isFresh(mixed $value, int $seconds): bool
    {
        $date = $this->date($value);

        return $date instanceof CarbonInterface
            && $date->getTimestamp() >= now()->getTimestamp() - $seconds;
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<int,mixed> ...$dateGroups */
    private function latestCheckedAt(array ...$dateGroups): ?CarbonInterface
    {
        $latest = null;

        foreach ($dateGroups as $dates) {
            foreach ($dates as $date) {
                if (! $date instanceof CarbonInterface) {
                    continue;
                }

                if (! $latest instanceof CarbonInterface
                    || $date->getTimestamp() > $latest->getTimestamp()) {
                    $latest = $date;
                }
            }
        }

        return $latest;
    }

    private function statusStaleSeconds(): int
    {
        $configured = max(60, min(3600, (int) config('cms.server_monitor.status_stale_seconds', 180)));

        return max($configured, $this->settings->refreshIntervalSeconds() + 60);
    }

    private function onlineStaleSeconds(): int
    {
        $configured = max(60, min(3600, (int) config('cms.server_monitor.online_stale_seconds', 300)));

        return max($configured, $this->settings->refreshIntervalSeconds() + 60);
    }
}
