<?php

namespace App\Services\Rewards;

use App\Contracts\GameRewardDeliveryGateway;
use App\Models\GameServer;
use App\Services\GameWorld\GameWorldDriverResolver;
use App\Support\Rewards\RewardDeliveryCapabilities;
use App\Support\Rewards\RewardDeliveryPayload;
use App\Support\Rewards\RewardDeliveryResult;
use Throwable;

final class DriverGameRewardDeliveryGateway implements GameRewardDeliveryGateway
{
    public function __construct(private readonly GameWorldDriverResolver $drivers) {}

    public function capabilities(GameServer $server): RewardDeliveryCapabilities
    {
        try {
            return $this->drivers->resolve($server)->rewardDeliveryCapabilities($server);
        } catch (Throwable) {
            return RewardDeliveryCapabilities::unsupported();
        }
    }

    public function deliver(GameServer $server, RewardDeliveryPayload $payload): RewardDeliveryResult
    {
        return $this->drivers->resolve($server)->deliverRewards($server, $payload);
    }

    public function status(GameServer $server, string $operationUuid): RewardDeliveryResult
    {
        return $this->drivers->resolve($server)->rewardDeliveryStatus($server, $operationUuid);
    }
}
