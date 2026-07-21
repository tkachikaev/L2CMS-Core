<?php

namespace App\Contracts;

use App\Models\GameServer;
use App\Support\Rewards\RewardDeliveryCapabilities;
use App\Support\Rewards\RewardDeliveryPayload;
use App\Support\Rewards\RewardDeliveryResult;

interface GameWorldDriver
{
    /** @return list<string> */
    public function capabilities(): array;

    /** @return list<array<string,mixed>> */
    public function ranking(GameServer $server, string $section, int $limit): array;

    /** @return list<array<string,mixed>> */
    public function heroes(GameServer $server): array;

    /** @return list<array<string,mixed>> */
    public function castleOwners(GameServer $server): array;

    /** @return list<array<string,mixed>> */
    public function charactersForAccount(GameServer $server, string $accountName): array;

    public function rewardDeliveryCapabilities(GameServer $server): RewardDeliveryCapabilities;

    public function deliverRewards(GameServer $server, RewardDeliveryPayload $payload): RewardDeliveryResult;

    public function rewardDeliveryStatus(GameServer $server, string $operationUuid): RewardDeliveryResult;
}
