<?php

namespace App\Services\GameWorld;

use App\Contracts\GameServerDatabaseGateway;
use App\Contracts\GameWorldDriver;
use App\Models\GameServer;
use App\Support\Rewards\RewardDeliveryCapabilities;
use App\Support\Rewards\RewardDeliveryPayload;
use App\Support\Rewards\RewardDeliveryResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Throwable;

final class MobiusInterludeGameWorldDriver implements GameWorldDriver
{
    private const RANKINGS = ['level', 'pvp', 'pk', 'play_time'];

    public function __construct(private readonly GameServerDatabaseGateway $database) {}

    public function capabilities(): array
    {
        return ['level', 'pvp', 'pk', 'play_time', 'heroes', 'castles'];
    }

    public function ranking(GameServer $server, string $section, int $limit): array
    {
        if (! in_array($section, self::RANKINGS, true)) {
            throw new InvalidArgumentException('Unsupported character ranking section.');
        }

        /** @var list<array<string,mixed>> $rows */
        $rows = $this->database->run($server, function (Connection $connection) use ($section, $limit): array {
            $query = $this->charactersQuery($connection);

            match ($section) {
                'level' => $query->orderByDesc('characters.level')->orderByDesc('characters.exp'),
                'pvp' => $query->where('characters.pvpkills', '>', 0)->orderByDesc('characters.pvpkills')->orderByDesc('characters.level'),
                'pk' => $query->where('characters.pkkills', '>', 0)->orderByDesc('characters.pkkills')->orderByDesc('characters.level'),
                'play_time' => $query
                    ->where('characters.onlinetime', '>', 0)
                    ->orderByDesc('characters.onlinetime')
                    ->orderByDesc('characters.level'),
            };

            return $query
                ->orderBy('characters.char_name')
                ->limit($this->limit($limit))
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();
        });

        return $rows;
    }

    public function heroes(GameServer $server): array
    {
        /** @var list<array<string,mixed>> $rows */
        $rows = $this->database->run($server, function (Connection $connection): array {
            return $this->charactersQuery($connection)
                ->join('heroes', 'heroes.charId', '=', 'characters.charId')
                ->addSelect([
                    'heroes.count as hero_count',
                    'heroes.played as hero_played',
                    'heroes.claimed as hero_claimed',
                ])
                ->orderByDesc('characters.level')
                ->orderBy('characters.char_name')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();
        });

        return $rows;
    }

    public function castleOwners(GameServer $server): array
    {
        /** @var list<array<string,mixed>> $rows */
        $rows = $this->database->run($server, static function (Connection $connection): array {
            return $connection
                ->table('clan_data')
                ->join('castle', 'castle.id', '=', 'clan_data.hasCastle')
                ->leftJoin('characters as leader', 'leader.charId', '=', 'clan_data.leader_id')
                ->where('clan_data.hasCastle', '>', 0)
                ->select([
                    'castle.id as castle_id',
                    'castle.name as castle_name',
                    'clan_data.clan_id',
                    'clan_data.clan_name',
                    'clan_data.clan_level',
                    'clan_data.reputation_score',
                    'leader.char_name as leader_name',
                ])
                ->orderBy('castle.id')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();
        });

        return $rows;
    }

    public function charactersForAccount(GameServer $server, string $accountName): array
    {
        /** @var list<array<string,mixed>> $rows */
        $rows = $this->database->run($server, function (Connection $connection) use ($accountName): array {
            return $this->charactersQuery($connection)
                ->where('characters.account_name', $accountName)
                ->orderByDesc('characters.level')
                ->orderBy('characters.char_name')
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();
        });

        return $rows;
    }

    public function rewardDeliveryCapabilities(GameServer $server): RewardDeliveryCapabilities
    {
        return $this->database->run($server, static function (Connection $connection): RewardDeliveryCapabilities {
            $schema = $connection->getSchemaBuilder();
            if (! $schema->hasColumns('kaev_reward_bridge_state', [
                'bridge_key',
                'protocol_version',
                'last_heartbeat_at',
            ]) || ! $schema->hasColumns('kaev_reward_operations', [
                'operation_uuid',
                'payload_hash',
                'account_login',
                'character_id',
                'character_name',
                'status',
                'failure_code',
                'created_at',
                'updated_at',
                'started_at',
                'completed_at',
            ]) || ! $schema->hasColumns('kaev_reward_operation_items', [
                'operation_uuid',
                'line_number',
                'item_id',
                'amount',
            ])) {
                return RewardDeliveryCapabilities::unsupported('reward_bridge_not_installed');
            }

            $state = $connection->table('kaev_reward_bridge_state')
                ->where('bridge_key', RewardDeliveryCapabilities::MODE_MOBIUS_REWARD_BRIDGE_V2)
                ->first(['protocol_version', 'last_heartbeat_at']);

            if ($state === null || (int) $state->protocol_version !== 2) {
                return RewardDeliveryCapabilities::unsupported('reward_bridge_protocol_mismatch');
            }

            try {
                $heartbeatAt = $state->last_heartbeat_at === null
                    ? null
                    : CarbonImmutable::parse((string) $state->last_heartbeat_at, 'UTC');
            } catch (Throwable) {
                return RewardDeliveryCapabilities::unsupported('reward_bridge_offline');
            }

            if ($heartbeatAt === null || $heartbeatAt->lt(now('UTC')->subMinutes(2))) {
                return RewardDeliveryCapabilities::unsupported('reward_bridge_offline');
            }

            return RewardDeliveryCapabilities::mobiusRewardBridge();
        });
    }

    public function deliverRewards(GameServer $server, RewardDeliveryPayload $payload): RewardDeliveryResult
    {
        return $this->database->run($server, function (Connection $connection) use ($payload): RewardDeliveryResult {
            return $connection->transaction(function () use ($connection, $payload): RewardDeliveryResult {
                $items = [];
                foreach ($payload->items as $item) {
                    if ($item['item_id'] <= 0 || $item['amount'] <= 0) {
                        return RewardDeliveryResult::failed('invalid_reward_item');
                    }

                    $items[] = [
                        'item_id' => $item['item_id'],
                        'amount' => $item['amount'],
                    ];
                }

                if ($items === []) {
                    return RewardDeliveryResult::failed('empty_reward_delivery');
                }

                usort($items, static fn (array $left, array $right): int => [
                    $left['item_id'],
                    $left['amount'],
                ] <=> [
                    $right['item_id'],
                    $right['amount'],
                ]);

                $itemRows = array_map(static fn (array $item, int $index): array => [
                    'operation_uuid' => $payload->operationUuid,
                    'line_number' => $index + 1,
                    'item_id' => $item['item_id'],
                    'amount' => $item['amount'],
                ], $items, array_keys($items));

                $character = $connection->table('characters')
                    ->where('charId', $payload->characterId)
                    ->first(['account_name', 'online']);

                if ($character === null || (string) $character->account_name !== $payload->accountLogin) {
                    return RewardDeliveryResult::failed('character_not_owned');
                }

                if ((int) $character->online !== 0) {
                    return RewardDeliveryResult::failed('character_online');
                }

                $payloadHash = $this->rewardPayloadHash($payload, $items);
                $timestamp = now('UTC')->format('Y-m-d H:i:s');
                $inserted = $connection->table('kaev_reward_operations')->insertOrIgnore([
                    'operation_uuid' => $payload->operationUuid,
                    'payload_hash' => $payloadHash,
                    'account_login' => $payload->accountLogin,
                    'character_id' => $payload->characterId,
                    'character_name' => $payload->characterName,
                    'status' => 'pending',
                    'failure_code' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'started_at' => null,
                    'completed_at' => null,
                ]);

                $operation = $connection->table('kaev_reward_operations')
                    ->where('operation_uuid', $payload->operationUuid)
                    ->lockForUpdate()
                    ->first(['payload_hash', 'status', 'failure_code', 'updated_at']);

                if ($operation === null) {
                    return RewardDeliveryResult::unknown('reward_bridge_enqueue_unknown');
                }

                if (! hash_equals((string) $operation->payload_hash, $payloadHash)) {
                    return RewardDeliveryResult::failed('operation_payload_conflict');
                }

                if ($inserted === 1) {
                    $connection->table('kaev_reward_operation_items')->insert($itemRows);
                }

                return $this->mapBridgeStatus(
                    (string) $operation->status,
                    $operation->failure_code,
                    $operation->updated_at,
                );
            }, 3);
        });
    }

    public function rewardDeliveryStatus(GameServer $server, string $operationUuid): RewardDeliveryResult
    {
        return $this->database->run($server, function (Connection $connection) use ($operationUuid): RewardDeliveryResult {
            $operation = $connection->table('kaev_reward_operations')
                ->where('operation_uuid', $operationUuid)
                ->first(['status', 'failure_code', 'updated_at']);

            if ($operation === null) {
                return RewardDeliveryResult::unknown('reward_bridge_operation_missing');
            }

            return $this->mapBridgeStatus(
                (string) $operation->status,
                $operation->failure_code,
                $operation->updated_at,
            );
        });
    }

    /** @param list<array{item_id:int,amount:int}> $items */
    private function rewardPayloadHash(RewardDeliveryPayload $payload, array $items): string
    {
        $encoded = json_encode([
            'operation_uuid' => $payload->operationUuid,
            'character_id' => $payload->characterId,
            'character_name' => $payload->characterName,
            'account_login' => $payload->accountLogin,
            'items' => $items,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $encoded);
    }

    private function mapBridgeStatus(
        string $status,
        mixed $failureCode,
        mixed $updatedAt = null,
    ): RewardDeliveryResult {
        if ($status === 'processing') {
            try {
                $processingAt = is_string($updatedAt) && $updatedAt !== ''
                    ? CarbonImmutable::parse($updatedAt, 'UTC')
                    : null;
            } catch (Throwable) {
                return RewardDeliveryResult::unknown('reward_bridge_processing_stale');
            }

            return $processingAt !== null && $processingAt->gte(now('UTC')->subMinutes(2))
                ? RewardDeliveryResult::pending()
                : RewardDeliveryResult::unknown('reward_bridge_processing_stale');
        }

        return match ($status) {
            'pending' => RewardDeliveryResult::pending(),
            'delivered' => RewardDeliveryResult::delivered(),
            'failed' => RewardDeliveryResult::failed(
                is_string($failureCode) && $failureCode !== '' ? $failureCode : 'reward_bridge_failed',
            ),
            'uncertain' => RewardDeliveryResult::unknown(
                is_string($failureCode) && $failureCode !== '' ? $failureCode : 'reward_bridge_outcome_uncertain',
            ),
            default => RewardDeliveryResult::unknown('reward_bridge_status_unknown'),
        };
    }

    private function charactersQuery(Connection $connection): Builder
    {
        return $connection
            ->table('characters')
            ->leftJoin('clan_data', 'clan_data.clan_id', '=', 'characters.clanid')
            ->where('characters.accesslevel', 0)
            ->where('characters.deletetime', 0)
            ->select([
                'characters.charId as id',
                'characters.char_name as name',
                'characters.level',
                'characters.classid as class_id',
                'characters.race',
                'characters.sex as gender',
                'characters.title',
                'characters.online',
                'characters.lastAccess as last_seen_at_ms',
                'characters.onlinetime as play_time_seconds',
                'characters.pvpkills as pvp_kills',
                'characters.pkkills as pk_kills',
                'characters.karma',
                'characters.nobless as noble',
                'characters.clanid as clan_id',
                'clan_data.clan_name',
            ]);
    }

    private function limit(int $limit): int
    {
        return min(max($limit, 1), 100);
    }
}
