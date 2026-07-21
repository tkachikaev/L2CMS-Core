<?php

namespace App\Contracts;

use App\Models\GameServer;
use App\Support\Rewards\RewardDeliveryCapabilities;
use App\Support\Rewards\RewardDeliveryPayload;
use App\Support\Rewards\RewardDeliveryResult;

interface GameRewardDeliveryGateway
{
    public function capabilities(GameServer $server): RewardDeliveryCapabilities;

    public function deliver(GameServer $server, RewardDeliveryPayload $payload): RewardDeliveryResult;

    public function status(GameServer $server, string $operationUuid): RewardDeliveryResult;
}
