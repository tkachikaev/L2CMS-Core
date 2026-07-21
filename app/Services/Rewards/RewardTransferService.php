<?php

namespace App\Services\Rewards;

use App\Contracts\GameRewardQueueGateway;
use App\Exceptions\RewardTransferException;
use App\Models\GameServer;
use App\Models\RewardDelivery;
use App\Models\RewardDeliveryItem;
use App\Models\RewardInventoryItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Rewards\RewardQueuePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class RewardTransferService
{
    public function __construct(
        private readonly GameRewardQueueGateway $rewardQueue,
        private readonly RewardCharacterDirectory $characters,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<int>  $inventoryItemIds
     */
    public function queue(
        User $user,
        GameServer $server,
        array $inventoryItemIds,
        int $characterId,
        string $requestToken,
    ): RewardDelivery {
        $capabilities = $this->rewardQueue->capabilities($server);
        if (! $capabilities->supported) {
            throw new RewardTransferException(
                $this->unavailableMessageKey($capabilities->reasonCode),
                $capabilities->reasonCode ?? 'reward_queue_unavailable',
            );
        }

        $character = $this->characters->find($user, $server, $characterId);
        if ($character === null) {
            throw new RewardTransferException(
                'The selected character does not belong to your account on this server.',
                'character_not_owned',
            );
        }

        $inventoryItemIds = array_values(array_unique(array_map('intval', $inventoryItemIds)));
        if ($inventoryItemIds === [] || count($inventoryItemIds) > 50) {
            throw new RewardTransferException(
                'Select between 1 and 50 rewards for one transfer.',
                'invalid_selection',
            );
        }

        $delivery = DB::transaction(function () use (
            $user,
            $server,
            $inventoryItemIds,
            $character,
            $requestToken,
        ): RewardDelivery {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $existing = RewardDelivery::query()
                ->where('request_token', $requestToken)
                ->where('user_id', $user->id)
                ->first();
            if ($existing instanceof RewardDelivery) {
                return $existing->loadMissing('items');
            }

            $items = RewardInventoryItem::query()
                ->where('user_id', $user->id)
                ->where('game_server_id', $server->id)
                ->whereIn('id', $inventoryItemIds)
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count($inventoryItemIds)
                || $items->contains(static fn (RewardInventoryItem $item): bool => $item->status !== RewardInventoryItem::STATUS_AVAILABLE)) {
                throw new RewardTransferException(
                    'One or more selected rewards are unavailable. Refresh the page and try again.',
                    'items_unavailable',
                );
            }

            $delivery = RewardDelivery::query()->create([
                'operation_uuid' => (string) Str::uuid(),
                'request_token' => $requestToken,
                'user_id' => $user->id,
                'game_server_id' => $server->id,
                'user_game_account_id' => $character['account_id'],
                'character_id' => $character['id'],
                'character_name' => $character['name'],
                'account_login' => $character['account_login'],
                'status' => RewardDelivery::STATUS_PENDING,
                'requested_at' => now(),
            ]);

            foreach ($items as $item) {
                $delivery->items()->create([
                    'reward_inventory_item_id' => $item->id,
                    'item_id' => $item->item_id,
                    'item_name' => $item->item_name,
                    'amount' => $item->amount,
                ]);
            }

            RewardInventoryItem::query()
                ->whereIn('id', $items->modelKeys())
                ->update(['status' => RewardInventoryItem::STATUS_RESERVED]);

            return $delivery->load('items');
        }, 3);

        if ($delivery->status === RewardDelivery::STATUS_FAILED) {
            throw new RewardTransferException(
                'The reward could not be written to the GameServer queue. It remains available in your web inventory.',
                $delivery->failure_code ?? 'reward_queue_write_failed',
            );
        }

        if (in_array($delivery->status, [RewardDelivery::STATUS_QUEUED, RewardDelivery::STATUS_REVIEW], true)) {
            return $delivery;
        }

        try {
            $result = $this->rewardQueue->enqueue($server, new RewardQueuePayload(
                requestUuid: $delivery->operation_uuid,
                gameServerId: $server->id,
                cmsUserId: $user->id,
                accountName: $delivery->account_login,
                characterId: $delivery->character_id,
                characterName: $delivery->character_name,
                items: $delivery->items->map(static fn (RewardDeliveryItem $item): array => [
                    'item_id' => $item->item_id,
                    'amount' => $item->amount,
                ])->values()->all(),
            ));
        } catch (Throwable) {
            $this->markForReview($delivery->id, 'reward_queue_write_unknown');

            return $delivery->fresh(['items']);
        }

        if ($result->isQueued()) {
            $this->markQueued($delivery->id);
        } elseif ($result->isFailed()) {
            $this->failAndRestore($delivery->id, $result->failureCode ?? 'reward_queue_write_failed');

            throw new RewardTransferException(
                'The reward could not be written to the GameServer queue. It remains available in your web inventory.',
                $result->failureCode ?? 'reward_queue_write_failed',
            );
        } else {
            $this->markForReview($delivery->id, $result->failureCode ?? 'reward_queue_write_unknown');
        }

        return $delivery->fresh(['items']);
    }

    private function markQueued(int $deliveryId): void
    {
        $delivery = DB::transaction(function () use ($deliveryId): ?RewardDelivery {
            $delivery = RewardDelivery::query()->lockForUpdate()->find($deliveryId);
            if (! $delivery instanceof RewardDelivery || $delivery->status !== RewardDelivery::STATUS_PENDING) {
                return null;
            }

            $itemIds = $delivery->items()->pluck('reward_inventory_item_id');
            RewardInventoryItem::query()
                ->whereIn('id', $itemIds)
                ->where('status', RewardInventoryItem::STATUS_RESERVED)
                ->update([
                    'status' => RewardInventoryItem::STATUS_TRANSFERRED,
                    'transferred_at' => now(),
                ]);

            $delivery->forceFill([
                'status' => RewardDelivery::STATUS_QUEUED,
                'failure_code' => null,
                'queued_at' => now(),
            ])->save();

            return $delivery;
        }, 3);

        if ($delivery instanceof RewardDelivery) {
            $this->auditLogger->system(
                category: 'reward',
                action: 'reward.queue_written',
                target: $delivery,
                details: [
                    'user_id' => $delivery->user_id,
                    'game_server_id' => $delivery->game_server_id,
                    'character_id' => $delivery->character_id,
                ],
            );
        }
    }

    private function failAndRestore(int $deliveryId, string $failureCode): void
    {
        $delivery = DB::transaction(function () use ($deliveryId, $failureCode): ?RewardDelivery {
            $delivery = RewardDelivery::query()->lockForUpdate()->find($deliveryId);
            if (! $delivery instanceof RewardDelivery || $delivery->status !== RewardDelivery::STATUS_PENDING) {
                return null;
            }

            $itemIds = $delivery->items()->pluck('reward_inventory_item_id');
            RewardInventoryItem::query()
                ->whereIn('id', $itemIds)
                ->where('status', RewardInventoryItem::STATUS_RESERVED)
                ->update(['status' => RewardInventoryItem::STATUS_AVAILABLE]);

            $delivery->forceFill([
                'status' => RewardDelivery::STATUS_FAILED,
                'failure_code' => $failureCode,
                'queued_at' => null,
            ])->save();

            return $delivery;
        }, 3);

        if ($delivery instanceof RewardDelivery) {
            $this->auditLogger->system(
                category: 'reward',
                action: 'reward.queue_failed',
                target: $delivery,
                details: ['failure_code' => $failureCode],
            );
        }
    }

    private function markForReview(int $deliveryId, string $failureCode): void
    {
        $delivery = DB::transaction(function () use ($deliveryId, $failureCode): ?RewardDelivery {
            $delivery = RewardDelivery::query()->lockForUpdate()->find($deliveryId);
            if (! $delivery instanceof RewardDelivery || $delivery->status !== RewardDelivery::STATUS_PENDING) {
                return null;
            }

            $delivery->forceFill([
                'status' => RewardDelivery::STATUS_REVIEW,
                'failure_code' => $failureCode,
                'queued_at' => null,
            ])->save();

            return $delivery;
        }, 3);

        if ($delivery instanceof RewardDelivery) {
            $this->auditLogger->system(
                category: 'reward',
                action: 'reward.queue_review_required',
                target: $delivery,
                details: ['failure_code' => $failureCode],
            );
        }
    }

    private function unavailableMessageKey(?string $reasonCode): string
    {
        return match ($reasonCode) {
            'reward_queue_not_installed' => 'The kaev_reward_queue table is not installed in this GameServer database.',
            'reward_queue_schema_invalid' => 'The kaev_reward_queue table has an unsupported structure.',
            default => 'The GameServer reward queue is unavailable. Check the database connection.',
        };
    }
}
