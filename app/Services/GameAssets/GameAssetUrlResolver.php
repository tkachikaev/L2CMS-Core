<?php

namespace App\Services\GameAssets;

use App\Models\GameServer;
use Illuminate\Support\Facades\File;

final class GameAssetUrlResolver
{
    private const EXTENSIONS = ['webp', 'png', 'jpg', 'jpeg'];

    public function itemIcon(GameServer|int $server, int $itemId): ?string
    {
        if ($itemId <= 0) {
            return null;
        }

        return $this->resolve('items', $this->serverId($server), (string) $itemId);
    }

    public function characterAvatar(GameServer|int|null $server, string $key): ?string
    {
        $key = trim($key);
        if ($key === '' || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,189}\z/', $key) !== 1) {
            return null;
        }

        return $this->resolve('characters', $server === null ? null : $this->serverId($server), $key);
    }

    public function rootPath(): string
    {
        return rtrim((string) config('cms.game_assets.uploads_path', public_path('uploads/game-assets')), '\\/');
    }

    private function serverId(GameServer|int $server): int
    {
        return $server instanceof GameServer ? (int) $server->getKey() : $server;
    }

    private function resolve(string $category, ?int $serverId, string $key): ?string
    {
        $relativeCandidates = [];

        if ($serverId !== null && $serverId > 0) {
            $relativeCandidates[] = $category.'/servers/'.$serverId.'/'.$key;
        }

        $relativeCandidates[] = $category.'/common/'.$key;

        foreach ($relativeCandidates as $relativeBase) {
            foreach (self::EXTENSIONS as $extension) {
                $relativePath = $relativeBase.'.'.$extension;
                $absolutePath = $this->rootPath().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

                if (File::isFile($absolutePath)) {
                    return asset('uploads/game-assets/'.$relativePath);
                }
            }
        }

        return null;
    }
}
