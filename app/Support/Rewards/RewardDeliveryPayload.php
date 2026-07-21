<?php

namespace App\Support\Rewards;

/**
 * @phpstan-type PayloadItem array{item_id:int,amount:int}
 */
final readonly class RewardDeliveryPayload
{
    /**
     * @param  list<PayloadItem>  $items
     */
    public function __construct(
        public string $operationUuid,
        public int $characterId,
        public string $characterName,
        public string $accountLogin,
        public array $items,
    ) {}
}
