<?php

namespace App\Support\Rewards;

final readonly class RewardDeliveryResult
{
    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    private function __construct(
        public string $status,
        public ?string $failureCode,
    ) {}

    public static function delivered(): self
    {
        return new self(self::STATUS_DELIVERED, null);
    }

    public static function pending(): self
    {
        return new self(self::STATUS_PENDING, null);
    }

    public static function failed(string $failureCode): self
    {
        return new self(self::STATUS_FAILED, $failureCode);
    }

    public static function unknown(string $failureCode = 'delivery_outcome_unknown'): self
    {
        return new self(self::STATUS_UNKNOWN, $failureCode);
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isUnknown(): bool
    {
        return $this->status === self::STATUS_UNKNOWN;
    }
}
