<?php

namespace App\Support\Rewards;

final readonly class RewardDeliveryCapabilities
{
    public const MODE_MOBIUS_REWARD_BRIDGE_V2 = 'mobius_reward_bridge_v2';

    public function __construct(
        public bool $supported,
        public bool $requiresOfflineCharacter,
        public bool $supportsSimpleItems,
        public ?string $deliveryMode = null,
        public ?string $reasonCode = null,
    ) {}

    public static function unsupported(string $reasonCode = 'driver_unsupported'): self
    {
        return new self(
            supported: false,
            requiresOfflineCharacter: true,
            supportsSimpleItems: false,
            deliveryMode: null,
            reasonCode: $reasonCode,
        );
    }

    public static function mobiusRewardBridge(): self
    {
        return new self(
            supported: true,
            requiresOfflineCharacter: true,
            supportsSimpleItems: true,
            deliveryMode: self::MODE_MOBIUS_REWARD_BRIDGE_V2,
            reasonCode: null,
        );
    }
}
