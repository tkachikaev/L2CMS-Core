<?php

namespace App\Services\Rewards;

use App\Contracts\GameRewardDeliveryGateway;
use App\Exceptions\RewardTransferException;
use App\Jobs\ProcessRewardDelivery;
use App\Models\GameServer;
use App\Models\RewardDelivery;
use App\Models\RewardInventoryItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class RewardTransferService
{
    public function __construct(
        private readonly GameRewardDeliveryGateway $deliveryGateway,
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
        $capabilities = $this->deliveryGateway->capabilities($server);
        if (! $capabilities->supported || ! $capabilities->supportsSimpleItems) {
            throw new RewardTransferException(
                'Automatic reward delivery is not supported by this GameServer driver yet.',
                'driver_unsupported',
            );
        }

        $character = $this->characters->find($user, $server, $characterId);
        if ($character === null) {
            throw new RewardTransferException(
                'The selected character does not belong to your account on this server.',
                'character_not_owned',
            );
        }

        if ($capabilities->requiresOfflineCharacter && $character['online']) {
            throw new RewardTransferException(
                'The selected character must be offline before rewards can be transferred.',
                'character_online',
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
                return $existing;
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

        if ($delivery->wasRecentlyCreated) {
            try {
                ProcessRewardDelivery::dispatch($delivery->id)->afterCommit();
            } catch (Throwable $exception) {
                app(RewardDeliveryProcessor::class)->fail($delivery->id, 'queue_dispatch_failed');

                throw new RewardTransferException(
                    'The reward transfer could not be queued. The rewards remain in your web inventory.',
                    'queue_dispatch_failed',
                );
            }

            $this->auditLogger->success(
                category: 'reward',
                action: 'reward.delivery_queued',
                actor: $user,
                target: $delivery,
                details: [
                    'game_server_id' => $server->id,
                    'character_id' => $delivery->character_id,
                    'character_name' => $delivery->character_name,
                    'item_count' => $delivery->items->count(),
                ],
            );
        }

        return $delivery;
    }
}
