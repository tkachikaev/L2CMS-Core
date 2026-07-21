<?php

namespace Tests\Fakes;

use App\Contracts\GameRewardDeliveryGateway;
use App\Models\GameServer;
use App\Support\Rewards\RewardDeliveryCapabilities;
use App\Support\Rewards\RewardDeliveryPayload;
use App\Support\Rewards\RewardDeliveryResult;
use RuntimeException;

class FakeGameRewardDeliveryGateway implements GameRewardDeliveryGateway
{
    public bool $supported = true;

    public bool $requiresOfflineCharacter = true;

    public bool $deliverSuccessfully = true;

    public bool $deliverPending = false;

    public bool $deliverUnknown = false;

    public bool $throwDuringDelivery = false;

    public bool $throwDuringStatus = false;

    public string $statusOutcome = RewardDeliveryResult::STATUS_DELIVERED;

    public ?string $statusFailureCode = null;

    public int $statusCalls = 0;

    /** @var list<RewardDeliveryPayload> */
    public array $payloads = [];

    public function capabilities(GameServer $server): RewardDeliveryCapabilities
    {
        return new RewardDeliveryCapabilities(
            supported: $this->supported,
            requiresOfflineCharacter: $this->requiresOfflineCharacter,
            supportsSimpleItems: $this->supported,
            deliveryMode: $this->supported ? RewardDeliveryCapabilities::MODE_MOBIUS_REWARD_BRIDGE_V2 : null,
            reasonCode: $this->supported ? null : 'driver_unsupported',
        );
    }

    public function deliver(GameServer $server, RewardDeliveryPayload $payload): RewardDeliveryResult
    {
        $this->payloads[] = $payload;

        if ($this->throwDuringDelivery) {
            throw new RuntimeException('Simulated unknown delivery outcome.');
        }

        if ($this->deliverUnknown) {
            return RewardDeliveryResult::unknown('fake_delivery_unknown');
        }

        if ($this->deliverPending) {
            return RewardDeliveryResult::pending();
        }

        return $this->deliverSuccessfully
            ? RewardDeliveryResult::delivered()
            : RewardDeliveryResult::failed('fake_delivery_failed');
    }

    public function status(GameServer $server, string $operationUuid): RewardDeliveryResult
    {
        $this->statusCalls++;

        if ($this->throwDuringStatus) {
            throw new RuntimeException('Simulated status lookup failure.');
        }

        return match ($this->statusOutcome) {
            RewardDeliveryResult::STATUS_DELIVERED => RewardDeliveryResult::delivered(),
            RewardDeliveryResult::STATUS_PENDING => RewardDeliveryResult::pending(),
            RewardDeliveryResult::STATUS_FAILED => RewardDeliveryResult::failed($this->statusFailureCode ?? 'fake_delivery_failed'),
            default => RewardDeliveryResult::unknown($this->statusFailureCode ?? 'fake_delivery_unknown'),
        };
    }
}
