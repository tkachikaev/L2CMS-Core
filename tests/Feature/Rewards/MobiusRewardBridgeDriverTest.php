<?php

namespace Tests\Feature\Rewards;

use App\Models\GameServer;
use App\Services\GameWorld\MobiusInterludeGameWorldDriver;
use App\Support\Rewards\RewardDeliveryCapabilities;
use App\Support\Rewards\RewardDeliveryPayload;
use App\Support\Rewards\RewardDeliveryResult;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithServerFixtures;
use Tests\Fakes\FakeGameServerDatabaseGateway;
use Tests\TestCase;

class MobiusRewardBridgeDriverTest extends TestCase
{
    use InteractsWithServerFixtures;
    use RefreshDatabase;

    private string $databasePath;

    private MobiusInterludeGameWorldDriver $driver;

    private GameServer $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = database_path('reward-bridge-test.sqlite');
        @unlink($this->databasePath);
        touch($this->databasePath);
        config()->set('database.connections.reward_bridge_test', [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('reward_bridge_test');
        $this->createCharacterSchema();

        [, $this->server] = $this->freshMobiusServerPair();
        $this->driver = new MobiusInterludeGameWorldDriver(
            new FakeGameServerDatabaseGateway('reward_bridge_test'),
        );
    }

    protected function tearDown(): void
    {
        DB::purge('reward_bridge_test');
        @unlink($this->databasePath);

        parent::tearDown();
    }

    public function test_capabilities_require_installed_compatible_and_live_bridge(): void
    {
        $missing = $this->driver->rewardDeliveryCapabilities($this->server);
        $this->assertFalse($missing->supported);
        $this->assertSame('reward_bridge_not_installed', $missing->reasonCode);

        $this->createBridgeSchema();
        DB::connection('reward_bridge_test')->table('kaev_reward_bridge_state')->insert([
            'bridge_key' => RewardDeliveryCapabilities::MODE_MOBIUS_REWARD_BRIDGE_V2,
            'protocol_version' => 2,
            'last_heartbeat_at' => now('UTC')->subMinutes(3)->format('Y-m-d H:i:s'),
        ]);

        $offline = $this->driver->rewardDeliveryCapabilities($this->server);
        $this->assertFalse($offline->supported);
        $this->assertSame('reward_bridge_offline', $offline->reasonCode);

        DB::connection('reward_bridge_test')->table('kaev_reward_bridge_state')->update([
            'protocol_version' => 1,
            'last_heartbeat_at' => now('UTC')->format('Y-m-d H:i:s'),
        ]);

        $incompatible = $this->driver->rewardDeliveryCapabilities($this->server);
        $this->assertFalse($incompatible->supported);
        $this->assertSame('reward_bridge_protocol_mismatch', $incompatible->reasonCode);

        DB::connection('reward_bridge_test')->table('kaev_reward_bridge_state')->update([
            'protocol_version' => 2,
            'last_heartbeat_at' => 'not-a-date',
        ]);

        $malformedHeartbeat = $this->driver->rewardDeliveryCapabilities($this->server);
        $this->assertFalse($malformedHeartbeat->supported);
        $this->assertSame('reward_bridge_offline', $malformedHeartbeat->reasonCode);

        DB::connection('reward_bridge_test')->table('kaev_reward_bridge_state')->update([
            'last_heartbeat_at' => now('UTC')->format('Y-m-d H:i:s'),
        ]);

        $supported = $this->driver->rewardDeliveryCapabilities($this->server);
        $this->assertTrue($supported->supported);
        $this->assertTrue($supported->requiresOfflineCharacter);
        $this->assertTrue($supported->supportsSimpleItems);
        $this->assertSame(RewardDeliveryCapabilities::MODE_MOBIUS_REWARD_BRIDGE_V2, $supported->deliveryMode);
    }

    public function test_enqueue_is_idempotent_and_rejects_payload_reuse(): void
    {
        $this->createBridgeSchema();
        $this->seedCharacter('RewardPlayer', 500, false);
        $payload = $this->payload();

        $first = $this->driver->deliverRewards($this->server, $payload);
        $second = $this->driver->deliverRewards($this->server, $payload);
        $reordered = $this->driver->deliverRewards($this->server, new RewardDeliveryPayload(
            operationUuid: $payload->operationUuid,
            characterId: $payload->characterId,
            characterName: $payload->characterName,
            accountLogin: $payload->accountLogin,
            items: array_reverse($payload->items),
        ));

        $this->assertTrue($first->isPending());
        $this->assertTrue($second->isPending());
        $this->assertTrue($reordered->isPending());
        $this->assertSame(1, DB::connection('reward_bridge_test')->table('kaev_reward_operations')->count());
        $this->assertSame(2, DB::connection('reward_bridge_test')->table('kaev_reward_operation_items')->count());

        $conflict = $this->driver->deliverRewards($this->server, new RewardDeliveryPayload(
            operationUuid: $payload->operationUuid,
            characterId: $payload->characterId,
            characterName: $payload->characterName,
            accountLogin: $payload->accountLogin,
            items: [['item_id' => 57, 'amount' => 999]],
        ));

        $this->assertTrue($conflict->isFailed());
        $this->assertSame('operation_payload_conflict', $conflict->failureCode);
        $this->assertSame(2, DB::connection('reward_bridge_test')->table('kaev_reward_operation_items')->count());
    }

    public function test_enqueue_rechecks_account_ownership_and_offline_state(): void
    {
        $this->createBridgeSchema();
        $this->seedCharacter('OtherAccount', 500, false);

        $wrongOwner = $this->driver->deliverRewards($this->server, $this->payload());
        $this->assertTrue($wrongOwner->isFailed());
        $this->assertSame('character_not_owned', $wrongOwner->failureCode);

        DB::connection('reward_bridge_test')->table('characters')->where('charId', 500)->update([
            'account_name' => 'RewardPlayer',
            'online' => 1,
        ]);

        $online = $this->driver->deliverRewards($this->server, $this->payload());
        $this->assertTrue($online->isFailed());
        $this->assertSame('character_online', $online->failureCode);
        $this->assertSame(0, DB::connection('reward_bridge_test')->table('kaev_reward_operations')->count());
    }

    public function test_bridge_status_distinguishes_confirmed_failure_from_unknown_outcome(): void
    {
        $this->createBridgeSchema();
        $this->seedCharacter('RewardPlayer', 500, false);
        $payload = $this->payload();
        $this->driver->deliverRewards($this->server, $payload);

        DB::connection('reward_bridge_test')->table('kaev_reward_operations')
            ->where('operation_uuid', $payload->operationUuid)
            ->update([
                'status' => 'processing',
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        $this->assertTrue($this->driver->rewardDeliveryStatus($this->server, $payload->operationUuid)->isPending());

        DB::connection('reward_bridge_test')->table('kaev_reward_operations')
            ->where('operation_uuid', $payload->operationUuid)
            ->update(['updated_at' => now('UTC')->subMinutes(3)->format('Y-m-d H:i:s')]);
        $stale = $this->driver->rewardDeliveryStatus($this->server, $payload->operationUuid);
        $this->assertTrue($stale->isUnknown());
        $this->assertSame('reward_bridge_processing_stale', $stale->failureCode);

        DB::connection('reward_bridge_test')->table('kaev_reward_operations')
            ->where('operation_uuid', $payload->operationUuid)
            ->update([
                'status' => 'uncertain',
                'failure_code' => 'reward_bridge_outcome_uncertain',
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        $uncertain = $this->driver->rewardDeliveryStatus($this->server, $payload->operationUuid);
        $this->assertTrue($uncertain->isUnknown());
        $this->assertSame('reward_bridge_outcome_uncertain', $uncertain->failureCode);

        DB::connection('reward_bridge_test')->table('kaev_reward_operations')
            ->where('operation_uuid', $payload->operationUuid)
            ->update(['status' => 'delivered', 'failure_code' => null]);
        $this->assertTrue($this->driver->rewardDeliveryStatus($this->server, $payload->operationUuid)->isDelivered());

        DB::connection('reward_bridge_test')->table('kaev_reward_operations')
            ->where('operation_uuid', $payload->operationUuid)
            ->update(['status' => 'failed', 'failure_code' => 'item_not_found']);
        $failed = $this->driver->rewardDeliveryStatus($this->server, $payload->operationUuid);
        $this->assertTrue($failed->isFailed());
        $this->assertSame('item_not_found', $failed->failureCode);

        $missing = $this->driver->rewardDeliveryStatus($this->server, 'de8306d0-c9f7-47c3-809e-e1b314f1dbd9');
        $this->assertSame(RewardDeliveryResult::STATUS_UNKNOWN, $missing->status);
        $this->assertSame('reward_bridge_operation_missing', $missing->failureCode);
    }

    private function createCharacterSchema(): void
    {
        Schema::connection('reward_bridge_test')->create('characters', function (Blueprint $table): void {
            $table->string('account_name');
            $table->unsignedBigInteger('charId')->primary();
            $table->string('char_name');
            $table->integer('online')->default(0);
        });
    }

    private function createBridgeSchema(): void
    {
        $schema = Schema::connection('reward_bridge_test');
        $schema->create('kaev_reward_bridge_state', function (Blueprint $table): void {
            $table->string('bridge_key')->primary();
            $table->unsignedInteger('protocol_version');
            $table->dateTime('last_heartbeat_at')->nullable();
        });
        $schema->create('kaev_reward_operations', function (Blueprint $table): void {
            $table->uuid('operation_uuid')->primary();
            $table->char('payload_hash', 64);
            $table->string('account_login', 45);
            $table->unsignedBigInteger('character_id');
            $table->string('character_name');
            $table->string('status', 16);
            $table->string('failure_code', 64)->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
        });
        $schema->create('kaev_reward_operation_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_uuid');
            $table->unsignedInteger('line_number');
            $table->unsignedInteger('item_id');
            $table->unsignedBigInteger('amount');
            $table->unique(['operation_uuid', 'line_number']);
        });
    }

    private function seedCharacter(string $accountLogin, int $characterId, bool $online): void
    {
        DB::connection('reward_bridge_test')->table('characters')->insert([
            'account_name' => $accountLogin,
            'charId' => $characterId,
            'char_name' => 'DeliveryCharacter',
            'online' => $online ? 1 : 0,
        ]);
    }

    private function payload(): RewardDeliveryPayload
    {
        return new RewardDeliveryPayload(
            operationUuid: '7c622f5c-928a-4ee1-9b92-9d6f32a347b8',
            characterId: 500,
            characterName: 'DeliveryCharacter',
            accountLogin: 'RewardPlayer',
            items: [
                ['item_id' => 57, 'amount' => 1000000],
                ['item_id' => 4037, 'amount' => 10],
            ],
        );
    }
}
