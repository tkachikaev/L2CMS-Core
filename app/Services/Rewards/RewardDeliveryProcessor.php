<?php

namespace App\Services\Rewards;

use App\Contracts\GameRewardDeliveryGateway;
use App\Jobs\ConfirmRewardDelivery;
use App\Models\RewardDelivery;
use App\Models\RewardDeliveryItem;
use App\Models\RewardInventoryItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Rewards\RewardDeliveryPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RewardDeliveryProcessor
{
    public function __construct(
        private readonly GameRewardDeliveryGateway $deliveryGateway,
        private readonly RewardCharacterDirectory $characters,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function process(int $deliveryId): void
    {
        $delivery = DB::transaction(function () use ($deliveryId): ?RewardDelivery {
            $delivery = RewardDelivery::query()->lockForUpdate()->find($deliveryId);
            if (! $delivery instanceof RewardDelivery || $delivery->status !== RewardDelivery::STATUS_PENDING) {
                return null;
            }

            $delivery->forceFill([
                'status' => RewardDelivery::STATUS_PROCESSING,
                'started_at' => now(),
                'failure_code' => null,
            ])->save();

            return $delivery->load(['gameServer', 'items']);
        }, 3);

        if (! $delivery instanceof RewardDelivery) {
            return;
        }

        try {
            $server = $delivery->gameServer;
            $user = User::query()->find($delivery->user_id);
            if (! $user instanceof User) {
                $this->fail($delivery->id, 'user_missing');

                return;
            }

            $capabilities = $this->deliveryGateway->capabilities($server);
            if (! $capabilities->supported || ! $capabilities->supportsSimpleItems) {
                $this->fail($delivery->id, 'driver_unsupported');

                return;
            }

            $character = $this->characters->find($user, $server, $delivery->character_id);
            if ($character === null || $character['account_login'] !== $delivery->account_login) {
                $this->fail($delivery->id, 'character_not_owned');

                return;
            }

            if ($capabilities->requiresOfflineCharacter && $character['online']) {
                $this->fail($delivery->id, 'character_online');

                return;
            }

            $result = $this->deliveryGateway->deliver($server, new RewardDeliveryPayload(
                operationUuid: $delivery->operation_uuid,
                characterId: $delivery->character_id,
                characterName: $delivery->character_name,
                accountLogin: $delivery->account_login,
                items: $delivery->items->map(static fn (RewardDeliveryItem $item): array => [
                    'item_id' => $item->item_id,
                    'amount' => $item->amount,
                ])->values()->all(),
            ));

            if ($result->isDelivered()) {
                $this->complete($delivery->id);

                return;
            }

            if ($result->isFailed()) {
                $this->fail($delivery->id, $result->failureCode ?? 'driver_failed');

                return;
            }

            if ($result->isUnknown()) {
                $this->markForReview($delivery->id, $result->failureCode ?? 'delivery_outcome_unknown');

                return;
            }

            $this->scheduleConfirmation($delivery->id);
        } catch (Throwable $exception) {
            Log::warning('Reward delivery enqueue outcome requires confirmation.', [
                'delivery_id' => $delivery->id,
                'exception' => $exception::class,
            ]);
            $this->scheduleConfirmation($delivery->id);
        }
    }

    public function confirm(int $deliveryId): bool
    {
        $delivery = RewardDelivery::query()
            ->with('gameServer')
            ->whereKey($deliveryId)
            ->where('status', RewardDelivery::STATUS_PROCESSING)
            ->first();

        if (! $delivery instanceof RewardDelivery) {
            return true;
        }

        $result = $this->deliveryGateway->status($delivery->gameServer, $delivery->operation_uuid);
        if ($result->isPending()) {
            return false;
        }

        if ($result->isDelivered()) {
            $this->complete($delivery->id);

            return true;
        }

        if ($result->isFailed()) {
            $this->fail($delivery->id, $result->failureCode ?? 'driver_failed');

            return true;
        }

        $this->markForReview($delivery->id, $result->failureCode ?? 'delivery_outcome_unknown');

        return true;
    }

    public function complete(int $deliveryId): void
    {
        $delivery = DB::transaction(function () use ($deliveryId): ?RewardDelivery {
            $delivery = RewardDelivery::query()->lockForUpdate()->find($deliveryId);
            if (! $delivery instanceof RewardDelivery || $delivery->status !== RewardDelivery::STATUS_PROCESSING) {
                return null;
            }

            $itemIds = $delivery->items()->pluck('reward_inventory_item_id');
            RewardInventoryItem::query()
                ->whereIn('id', $itemIds)
                ->where('status', RewardInventoryItem::STATUS_RESERVED)
                ->update([
                    'status' => RewardInventoryItem::STATUS_DELIVERED,
                    'delivered_at' => now(),
                ]);

            $delivery->forceFill([
                'status' => RewardDelivery::STATUS_DELIVERED,
                'failure_code' => null,
                'completed_at' => now(),
            ])->save();

            return $delivery;
        }, 3);

        if ($delivery instanceof RewardDelivery) {
            $this->auditLogger->system(
                category: 'reward',
                action: 'reward.delivery_completed',
                target: $delivery,
                details: [
                    'user_id' => $delivery->user_id,
                    'game_server_id' => $delivery->game_server_id,
                    'character_id' => $delivery->character_id,
                ],
            );
        }
    }

    public function fail(int $deliveryId, string $failureCode): void
    {
        $delivery = DB::transaction(function () use ($deliveryId, $failureCode): ?RewardDelivery {
            $delivery = RewardDelivery::query()->lockForUpdate()->find($deliveryId);
            if (! $delivery instanceof RewardDelivery
                || ! in_array($delivery->status, [RewardDelivery::STATUS_PENDING, RewardDelivery::STATUS_PROCESSING], true)) {
                return null;
            }

            $itemIds = $delivery->items()->pluck('reward_inventory_item_id');
            RewardInventoryItem::query()
                ->whereIn('id', $itemIds)
                ->where('status', RewardInventoryItem::STATUS_RESERVED)
                ->update(['status' => RewardInventoryItem::STATUS_AVAILABLE]);

            $delivery->forceFill([
                'status' => RewardDelivery::STATUS_FAILED,
                'failure_code' => $this->safeFailureCode($failureCode),
                'completed_at' => now(),
            ])->save();

            return $delivery;
        }, 3);

        if ($delivery instanceof RewardDelivery) {
            $this->auditLogger->system(
                category: 'reward',
                action: 'reward.delivery_failed',
                target: $delivery,
                details: [
                    'user_id' => $delivery->user_id,
                    'game_server_id' => $delivery->game_server_id,
                    'failure_code' => $delivery->failure_code,
                ],
                result: 'failed',
            );
        }
    }

    public function markForReview(int $deliveryId, string $failureCode): void
    {
        $delivery = DB::transaction(function () use ($deliveryId, $failureCode): ?RewardDelivery {
            $delivery = RewardDelivery::query()->lockForUpdate()->find($deliveryId);
            if (! $delivery instanceof RewardDelivery
                || ! in_array($delivery->status, [RewardDelivery::STATUS_PENDING, RewardDelivery::STATUS_PROCESSING], true)) {
                return null;
            }

            $delivery->forceFill([
                'status' => RewardDelivery::STATUS_REVIEW,
                'failure_code' => $this->safeFailureCode($failureCode),
                'completed_at' => null,
            ])->save();

            return $delivery;
        }, 3);

        if ($delivery instanceof RewardDelivery) {
            $this->auditLogger->system(
                category: 'reward',
                action: 'reward.delivery_review_required',
                target: $delivery,
                details: [
                    'user_id' => $delivery->user_id,
                    'game_server_id' => $delivery->game_server_id,
                    'failure_code' => $delivery->failure_code,
                ],
                result: 'failed',
            );
        }
    }

    private function scheduleConfirmation(int $deliveryId): void
    {
        ConfirmRewardDelivery::dispatch($deliveryId)
            ->delay(now()->addSeconds(5));
    }

    private function safeFailureCode(string $failureCode): string
    {
        $failureCode = strtolower(trim($failureCode));

        return preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/', $failureCode) === 1
            ? $failureCode
            : 'delivery_failed';
    }
}
