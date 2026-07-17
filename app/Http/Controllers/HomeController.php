<?php

namespace App\Http\Controllers;

use App\Contracts\GameServerAdapter;
use App\Models\News;
use App\Services\GameServerSettings;
use App\Services\Servers\ServerMonitorCoordinator;
use App\Services\Servers\ServerStatusOverview;
use Illuminate\View\View;

final class HomeController
{
    public function __invoke(
        GameServerAdapter $game,
        GameServerSettings $gameServerSettings,
        ServerStatusOverview $statuses,
        ServerMonitorCoordinator $monitorCoordinator,
    ): View {
        $news = News::query()->with('translations')->published()->latest('published_at')->limit(3)->get();
        $monitor = $statuses->get();
        $statusById = collect($monitor['game_servers'])->keyBy('id');
        $servers = array_map(
            static function (array $server) use ($statusById): array {
                $status = $statusById->get($server['id']);
                if (! is_array($status)) {
                    $status = [
                        'state' => 'unknown',
                        'availability_state' => 'unknown',
                        'players' => null,
                        'public_players' => null,
                        'maintenance_message' => $server['maintenance_message'],
                        'checked_at' => null,
                    ];
                }

                return array_merge($server, $status, [
                    'state' => $status['availability_state'] ?? 'unknown',
                ]);
            },
            $gameServerSettings->all(),
        );

        return view('theme::home', [
            'news' => $news,
            'server' => $servers[0] ?? null,
            'servers' => $servers,
            'topCharacters' => $game->topCharacters(),
            'monitorRefreshDue' => $monitorCoordinator->isDue(),
            'publicOnlineVisible' => (bool) $monitor['public_online_visible'],
        ]);
    }
}
