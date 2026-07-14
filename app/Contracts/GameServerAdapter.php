<?php

namespace App\Contracts;

interface GameServerAdapter
{
    public function status(): array;

    public function topCharacters(int $limit = 5): array;

    public function charactersForAccount(string $accountName): array;

    public function accountExists(string $accountName): bool;
}
