<?php

namespace App\Http\Controllers;

use App\Contracts\GameServerAdapter;
use App\Models\News;
use App\Services\GameServerSettings;
use Illuminate\View\View;

final class HomeController
{
    public function __invoke(GameServerAdapter $game, GameServerSettings $gameServerSettings): View
    {
        $news = News::query()->with('translations')->published()->latest('published_at')->limit(3)->get();
        $status = $game->status();
        $servers = array_map(
            static fn (array $server): array => array_merge($server, $status),
            $gameServerSettings->all(),
        );

        return view('theme::home', [
            'news' => $news,
            'server' => $servers[0] ?? null,
            'servers' => $servers,
            'topCharacters' => $game->topCharacters(),
        ]);
    }
}
