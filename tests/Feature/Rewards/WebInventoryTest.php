<?php

namespace Tests\Feature\Rewards;

use App\Contracts\GameAccountGateway;
use App\Contracts\GameRewardDeliveryGateway;
use App\Exceptions\GameServerHasRewardData;
use App\Jobs\ConfirmRewardDelivery;
use App\Jobs\ProcessRewardDelivery;
use App\Models\GameServer;
use App\Models\RewardDelivery;
use App\Models\RewardInventoryItem;
use App\Models\User;
use App\Models\UserGameAccount;
use App\Services\GameServerSettings;
use App\Services\Rewards\RewardDeliveryProcessor;
use App\Services\Rewards\RewardInventoryService;
use App\Support\Rewards\RewardGrantItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithServerFixtures;
use Tests\Fakes\FakeGameAccountGateway;
use Tests\Fakes\FakeGameRewardDeliveryGateway;
use Tests\TestCase;

class WebInventoryTest extends TestCase
{
    use InteractsWithServerFixtures;
    use RefreshDatabase;

    private FakeGameAccountGateway $gameAccounts;

    private FakeGameRewardDeliveryGateway $deliveryGateway;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->gameAccounts = new FakeGameAccountGateway;
        $this->deliveryGateway = new FakeGameRewardDeliveryGateway;
        $this->app->instance(GameAccountGateway::class, $this->gameAccounts);
        $this->app->instance(GameRewardDeliveryGateway::class, $this->deliveryGateway);
    }

    public function test_reward_jobs_use_durable_database_queue(): void
    {
        $process = new ProcessRewardDelivery(1);
        $confirm = new ConfirmRewardDelivery(1);

        $this->assertSame('database', $process->connection);
        $this->assertSame('rewards', $process->queue);
        $this->assertSame('database', $confirm->connection);
        $this->assertSame('rewards', $confirm->queue);
    }

    public function test_grants_are_idempotent_and_separated_by_server(): void
    {
        $user = User::factory()->create();
        [, $server] = $this->freshMobiusServerPair();
        $otherServer = $server->replicate(['id'])->fill(['name' => 'High Five x5']);
        $otherServer->save();
        $inventory = app(RewardInventoryService::class);

        $first = $inventory->grant(
            user: $user,
            server: $server,
            grantKey: 'promo:activation:100',
            sourceType: 'promo_code',
            items: [new RewardGrantItem(57, 1000000, 'Adena')],
        );
        $repeated = $inventory->grant(
            user: $user,
            server: $server,
            grantKey: 'promo:activation:100',
            sourceType: 'promo_code',
            items: [new RewardGrantItem(57, 1000000, 'Adena')],
        );
        $inventory->grant(
            user: $user,
            server: $otherServer,
            grantKey: 'promo:activation:101',
            sourceType: 'promo_code',
            items: [new RewardGrantItem(4037, 5, 'Coin of Luck')],
        );

        $this->assertSame($first->id, $repeated->id);
        $this->assertDatabaseCount('reward_inventory_grants', 2);
        $this->assertDatabaseCount('reward_inventory_items', 2);
        $this->assertDatabaseHas('reward_inventory_items', [
            'user_id' => $user->id,
            'game_server_id' => $server->id,
            'item_id' => 57,
            'amount' => 1000000,
            'status' => RewardInventoryItem::STATUS_AVAILABLE,
        ]);
    }

    public function test_game_server_with_reward_data_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        [, $server] = $this->freshMobiusServerPair();
        app(RewardInventoryService::class)->grant(
            user: $user,
            server: $server,
            grantKey: 'admin-gift:server-delete-guard',
            sourceType: 'admin_gift',
            items: [new RewardGrantItem(57, 1000, 'Adena')],
        );

        try {
            app(GameServerSettings::class)->delete($server);
            $this->fail('A GameServer with reward data must not be deleted.');
        } catch (GameServerHasRewardData) {
            $this->assertDatabaseHas('game_servers', ['id' => $server->id]);
            $this->assertDatabaseHas('reward_inventory_items', [
                'user_id' => $user->id,
                'game_server_id' => $server->id,
                'status' => RewardInventoryItem::STATUS_AVAILABLE,
            ]);
        }
    }

    public function test_web_inventory_routes_require_authentication_and_support_locales(): void
    {
        $this->get('/account/web-inventory')->assertRedirect(route('login'));

        $user = User::factory()->create(['locale' => 'ru']);

        $this->actingAs($user)
            ->get('/account/web-inventory')
            ->assertOk()
            ->assertSee('Веб-инвентарь');

        $this->actingAs($user)
            ->get('/ru/account/web-inventory')
            ->assertOk()
            ->assertSee('Веб-инвентарь');
    }

    public function test_transfer_reserves_items_and_dispatches_one_idempotent_operation(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        [, $server] = $this->freshMobiusServerPair();
        $account = UserGameAccount::factory()->for($user)->registeredOn($server)->create([
            'game_login' => 'RewardPlayer',
            'normalized_login' => 'rewardplayer',
        ]);
        $this->gameAccounts->charactersByServer[$server->id] = [[
            'id' => 9001,
            'name' => 'Elvenka',
            'level' => 76,
            'class_id' => 25,
            'race' => 1,
            'gender' => 1,
            'title' => null,
            'online' => false,
            'clan' => null,
            'last_access' => 0,
            'play_time_seconds' => 0,
            'pvp_kills' => 0,
            'pk_kills' => 0,
            'karma' => 0,
            'noble' => false,
            'hero' => false,
            'created_at' => null,
        ]];
        $grant = app(RewardInventoryService::class)->grant(
            user: $user,
            server: $server,
            grantKey: 'promo:activation:200',
            sourceType: 'promo_code',
            items: [
                new RewardGrantItem(57, 1000000, 'Adena'),
                new RewardGrantItem(4037, 10, 'Coin of Luck'),
            ],
        );
        $itemIds = $grant->items->modelKeys();
        $token = '7d533cac-9e67-4a4d-884f-4f18c18498b5';

        $payload = [
            'game_server_id' => $server->id,
            'character_id' => 9001,
            'inventory_item_ids' => $itemIds,
            'request_token' => $token,
        ];

        $this->actingAs($user)->post('/account/web-inventory/transfers', $payload)->assertRedirect();
        $this->actingAs($user)->post('/account/web-inventory/transfers', $payload)->assertRedirect();

        $this->assertDatabaseCount('reward_deliveries', 1);
        $this->assertDatabaseHas('reward_deliveries', [
            'request_token' => $token,
            'user_id' => $user->id,
            'game_server_id' => $server->id,
            'user_game_account_id' => $account->id,
            'character_id' => 9001,
            'status' => RewardDelivery::STATUS_PENDING,
        ]);
        $this->assertSame(2, RewardInventoryItem::query()->where('status', RewardInventoryItem::STATUS_RESERVED)->count());
        Queue::assertPushed(ProcessRewardDelivery::class, 1);
    }

    public function test_successful_processing_delivers_items_once(): void
    {
        Queue::fake();
        [$user, $server, $delivery] = $this->preparedDelivery();

        app(RewardDeliveryProcessor::class)->process($delivery->id);
        app(RewardDeliveryProcessor::class)->process($delivery->id);

        $this->assertSame(RewardDelivery::STATUS_DELIVERED, $delivery->fresh()->status);
        $this->assertSame(RewardInventoryItem::STATUS_DELIVERED, $delivery->items->first()->inventoryItem->fresh()->status);
        $this->assertCount(1, $this->deliveryGateway->payloads);
        $this->assertSame($user->id, $delivery->user_id);
        $this->assertSame($server->id, $delivery->game_server_id);
    }

    public function test_failed_processing_returns_items_to_inventory(): void
    {
        Queue::fake();
        $this->deliveryGateway->deliverSuccessfully = false;
        [, , $delivery] = $this->preparedDelivery();

        app(RewardDeliveryProcessor::class)->process($delivery->id);

        $this->assertSame(RewardDelivery::STATUS_FAILED, $delivery->fresh()->status);
        $this->assertSame('fake_delivery_failed', $delivery->fresh()->failure_code);
        $this->assertSame(RewardInventoryItem::STATUS_AVAILABLE, $delivery->items->first()->inventoryItem->fresh()->status);
    }

    public function test_unknown_enqueue_outcome_keeps_items_reserved_and_requests_confirmation(): void
    {
        Queue::fake();
        $this->deliveryGateway->throwDuringDelivery = true;
        [, , $delivery] = $this->preparedDelivery();

        app(RewardDeliveryProcessor::class)->process($delivery->id);

        $this->assertSame(RewardDelivery::STATUS_PROCESSING, $delivery->fresh()->status);
        $this->assertSame(RewardInventoryItem::STATUS_RESERVED, $delivery->items->first()->inventoryItem->fresh()->status);
        Queue::assertPushed(ConfirmRewardDelivery::class, 1);
    }

    public function test_unknown_initial_bridge_result_requires_review_without_returning_items(): void
    {
        Queue::fake();
        $this->deliveryGateway->deliverUnknown = true;
        [, , $delivery] = $this->preparedDelivery();

        app(RewardDeliveryProcessor::class)->process($delivery->id);

        $this->assertSame(RewardDelivery::STATUS_REVIEW, $delivery->fresh()->status);
        $this->assertSame('fake_delivery_unknown', $delivery->fresh()->failure_code);
        $this->assertSame(RewardInventoryItem::STATUS_RESERVED, $delivery->items->first()->inventoryItem->fresh()->status);
        Queue::assertNotPushed(ConfirmRewardDelivery::class);
    }

    public function test_pending_bridge_delivery_is_confirmed_without_duplicate_enqueue(): void
    {
        Queue::fake();
        $this->deliveryGateway->deliverPending = true;
        [, , $delivery] = $this->preparedDelivery();

        $processor = app(RewardDeliveryProcessor::class);
        $processor->process($delivery->id);

        $this->assertSame(RewardDelivery::STATUS_PROCESSING, $delivery->fresh()->status);
        $this->assertCount(1, $this->deliveryGateway->payloads);
        Queue::assertPushed(ConfirmRewardDelivery::class, 1);

        $this->assertTrue($processor->confirm($delivery->id));
        $this->assertSame(RewardDelivery::STATUS_DELIVERED, $delivery->fresh()->status);
        $this->assertSame(RewardInventoryItem::STATUS_DELIVERED, $delivery->items->first()->inventoryItem->fresh()->status);
        $this->assertCount(1, $this->deliveryGateway->payloads);
        $this->assertSame(1, $this->deliveryGateway->statusCalls);
    }

    public function test_confirmed_bridge_failure_returns_items_to_inventory(): void
    {
        Queue::fake();
        $this->deliveryGateway->deliverPending = true;
        $this->deliveryGateway->statusOutcome = 'failed';
        $this->deliveryGateway->statusFailureCode = 'item_not_found';
        [, , $delivery] = $this->preparedDelivery();

        $processor = app(RewardDeliveryProcessor::class);
        $processor->process($delivery->id);
        $this->assertTrue($processor->confirm($delivery->id));

        $this->assertSame(RewardDelivery::STATUS_FAILED, $delivery->fresh()->status);
        $this->assertSame('item_not_found', $delivery->fresh()->failure_code);
        $this->assertSame(RewardInventoryItem::STATUS_AVAILABLE, $delivery->items->first()->inventoryItem->fresh()->status);
    }

    public function test_unknown_bridge_status_requires_review_without_returning_items(): void
    {
        Queue::fake();
        $this->deliveryGateway->deliverPending = true;
        $this->deliveryGateway->statusOutcome = 'unknown';
        [, , $delivery] = $this->preparedDelivery();

        $processor = app(RewardDeliveryProcessor::class);
        $processor->process($delivery->id);
        $this->assertTrue($processor->confirm($delivery->id));

        $this->assertSame(RewardDelivery::STATUS_REVIEW, $delivery->fresh()->status);
        $this->assertSame('fake_delivery_unknown', $delivery->fresh()->failure_code);
        $this->assertSame(RewardInventoryItem::STATUS_RESERVED, $delivery->items->first()->inventoryItem->fresh()->status);
    }

    public function test_user_cannot_transfer_another_users_reward_or_character(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        [, $server] = $this->freshMobiusServerPair();
        UserGameAccount::factory()->for($attacker)->registeredOn($server)->create();
        $this->gameAccounts->charactersByServer[$server->id] = [[
            'id' => 777,
            'name' => 'AttackerCharacter',
            'level' => 1,
            'class_id' => 0,
            'race' => 0,
            'gender' => 0,
            'title' => null,
            'online' => false,
            'clan' => null,
            'last_access' => 0,
            'play_time_seconds' => 0,
            'pvp_kills' => 0,
            'pk_kills' => 0,
            'karma' => 0,
            'noble' => false,
            'hero' => false,
            'created_at' => null,
        ]];
        $grant = app(RewardInventoryService::class)->grant(
            user: $owner,
            server: $server,
            grantKey: 'gift:owner:1',
            sourceType: 'admin_gift',
            items: [new RewardGrantItem(57, 100, 'Adena')],
        );

        $this->actingAs($attacker)->post('/account/web-inventory/transfers', [
            'game_server_id' => $server->id,
            'character_id' => 777,
            'inventory_item_ids' => [$grant->items->first()->id],
            'request_token' => 'f73f998c-40ff-4b03-a02c-688654c5db04',
        ])->assertSessionHasErrors('inventory');

        $this->assertDatabaseCount('reward_deliveries', 0);
        $this->assertSame(RewardInventoryItem::STATUS_AVAILABLE, $grant->items->first()->fresh()->status);
    }

    /** @return array{User,GameServer,RewardDelivery} */
    private function preparedDelivery(): array
    {
        $user = User::factory()->create();
        [, $server] = $this->freshMobiusServerPair();
        UserGameAccount::factory()->for($user)->registeredOn($server)->create([
            'game_login' => 'DeliveryPlayer',
            'normalized_login' => 'deliveryplayer',
        ]);
        $this->gameAccounts->charactersByServer[$server->id] = [[
            'id' => 500,
            'name' => 'DeliveryCharacter',
            'level' => 80,
            'class_id' => 0,
            'race' => 0,
            'gender' => 0,
            'title' => null,
            'online' => false,
            'clan' => null,
            'last_access' => 0,
            'play_time_seconds' => 0,
            'pvp_kills' => 0,
            'pk_kills' => 0,
            'karma' => 0,
            'noble' => false,
            'hero' => false,
            'created_at' => null,
        ]];
        $grant = app(RewardInventoryService::class)->grant(
            user: $user,
            server: $server,
            grantKey: 'promo:activation:500',
            sourceType: 'promo_code',
            items: [new RewardGrantItem(57, 5000, 'Adena')],
        );

        $this->actingAs($user)->post('/account/web-inventory/transfers', [
            'game_server_id' => $server->id,
            'character_id' => 500,
            'inventory_item_ids' => $grant->items->modelKeys(),
            'request_token' => 'b6b6d3bc-f924-4dbc-bc26-f324404f7f23',
        ])->assertRedirect();

        $delivery = RewardDelivery::query()->with('items.inventoryItem')->firstOrFail();

        return [$user, $server, $delivery];
    }
}
