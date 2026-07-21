<?php

namespace Tests\Feature\Rewards;

use App\Contracts\GameAccountGateway;
use App\Contracts\GameRewardQueueGateway;
use App\Exceptions\GameServerHasRewardData;
use App\Models\GameServer;
use App\Models\RewardDelivery;
use App\Models\RewardInventoryItem;
use App\Models\User;
use App\Models\UserGameAccount;
use App\Services\GameServerSettings;
use App\Services\Rewards\RewardInventoryService;
use App\Support\Rewards\RewardGrantItem;
use App\Support\Rewards\RewardQueueWriteResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithServerFixtures;
use Tests\Fakes\FakeGameAccountGateway;
use Tests\Fakes\FakeGameRewardQueueGateway;
use Tests\TestCase;

class WebInventoryTest extends TestCase
{
    use InteractsWithServerFixtures;
    use RefreshDatabase;

    private FakeGameAccountGateway $gameAccounts;

    private FakeGameRewardQueueGateway $rewardQueue;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->gameAccounts = new FakeGameAccountGateway;
        $this->rewardQueue = new FakeGameRewardQueueGateway;
        $this->app->instance(GameAccountGateway::class, $this->gameAccounts);
        $this->app->instance(GameRewardQueueGateway::class, $this->rewardQueue);
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

    public function test_missing_gameserver_queue_disables_transfer_with_clear_message(): void
    {
        $this->rewardQueue->supported = false;
        $this->rewardQueue->unsupportedReason = 'reward_queue_not_installed';
        [$user, $server] = $this->userWithCharacter();
        app(RewardInventoryService::class)->grant(
            user: $user,
            server: $server,
            grantKey: 'queue-missing:1',
            sourceType: 'admin_gift',
            items: [new RewardGrantItem(57, 1000, 'Adena')],
        );

        $this->actingAs($user)
            ->get('/account/web-inventory')
            ->assertOk()
            ->assertSee(__('The kaev_reward_queue table is not installed in this GameServer database.'));
    }

    public function test_transfer_writes_one_idempotent_queue_payload_and_allows_online_character(): void
    {
        [$user, $server, $account] = $this->userWithCharacter(online: true);
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
        $token = '7d533cac-9e67-4a4d-884f-4f18c18498b5';
        $payload = [
            'game_server_id' => $server->id,
            'character_id' => 9001,
            'inventory_item_ids' => $grant->items->modelKeys(),
            'request_token' => $token,
        ];

        $this->actingAs($user)->post('/account/web-inventory/transfers', $payload)->assertRedirect();
        $this->actingAs($user)->post('/account/web-inventory/transfers', $payload)->assertRedirect();

        $delivery = RewardDelivery::query()->with('items')->firstOrFail();
        $this->assertDatabaseCount('reward_deliveries', 1);
        $this->assertSame(RewardDelivery::STATUS_QUEUED, $delivery->status);
        $this->assertSame($account->id, $delivery->user_game_account_id);
        $this->assertSame(2, RewardInventoryItem::query()->where('status', RewardInventoryItem::STATUS_TRANSFERRED)->count());
        $this->assertCount(1, $this->rewardQueue->payloads);
        $this->assertSame($delivery->operation_uuid, $this->rewardQueue->payloads[0]->requestUuid);
        $this->assertSame($server->id, $this->rewardQueue->payloads[0]->gameServerId);
        $this->assertSame('RewardPlayer', $this->rewardQueue->payloads[0]->accountName);
        $this->assertSame(9001, $this->rewardQueue->payloads[0]->characterId);
        $this->assertSame([
            ['item_id' => 57, 'amount' => 1000000],
            ['item_id' => 4037, 'amount' => 10],
        ], $this->rewardQueue->payloads[0]->items);
    }

    public function test_confirmed_queue_failure_returns_rewards_to_inventory(): void
    {
        $this->rewardQueue->outcome = RewardQueueWriteResult::STATUS_FAILED;
        $this->rewardQueue->failureCode = 'reward_queue_write_failed';
        [$user, $server] = $this->userWithCharacter();
        $grant = app(RewardInventoryService::class)->grant(
            user: $user,
            server: $server,
            grantKey: 'queue-failure:1',
            sourceType: 'admin_gift',
            items: [new RewardGrantItem(57, 1000, 'Adena')],
        );

        $this->actingAs($user)->post('/account/web-inventory/transfers', [
            'game_server_id' => $server->id,
            'character_id' => 9001,
            'inventory_item_ids' => $grant->items->modelKeys(),
            'request_token' => '2f21d884-9eb0-4e4d-a5a8-d3f0040940b8',
        ])->assertSessionHasErrors('inventory');

        $this->assertSame(RewardDelivery::STATUS_FAILED, RewardDelivery::query()->firstOrFail()->status);
        $this->assertSame(RewardInventoryItem::STATUS_AVAILABLE, $grant->items->first()->fresh()->status);
    }

    public function test_unknown_queue_write_keeps_rewards_reserved_for_review(): void
    {
        $this->rewardQueue->outcome = RewardQueueWriteResult::STATUS_UNKNOWN;
        [$user, $server] = $this->userWithCharacter();
        $grant = app(RewardInventoryService::class)->grant(
            user: $user,
            server: $server,
            grantKey: 'queue-unknown:1',
            sourceType: 'admin_gift',
            items: [new RewardGrantItem(57, 1000, 'Adena')],
        );

        $this->actingAs($user)->post('/account/web-inventory/transfers', [
            'game_server_id' => $server->id,
            'character_id' => 9001,
            'inventory_item_ids' => $grant->items->modelKeys(),
            'request_token' => '520dcbdf-7cbc-460e-8fde-3f24744abec0',
        ])->assertRedirect();

        $this->assertSame(RewardDelivery::STATUS_REVIEW, RewardDelivery::query()->firstOrFail()->status);
        $this->assertSame(RewardInventoryItem::STATUS_RESERVED, $grant->items->first()->fresh()->status);
    }

    public function test_user_cannot_transfer_another_users_reward_or_character(): void
    {
        $owner = User::factory()->create();
        [$attacker, $server] = $this->userWithCharacter();
        $grant = app(RewardInventoryService::class)->grant(
            user: $owner,
            server: $server,
            grantKey: 'gift:owner:1',
            sourceType: 'admin_gift',
            items: [new RewardGrantItem(57, 100, 'Adena')],
        );

        $this->actingAs($attacker)->post('/account/web-inventory/transfers', [
            'game_server_id' => $server->id,
            'character_id' => 9001,
            'inventory_item_ids' => [$grant->items->first()->id],
            'request_token' => 'f73f998c-40ff-4b03-a02c-688654c5db04',
        ])->assertSessionHasErrors('inventory');

        $this->assertDatabaseCount('reward_deliveries', 0);
        $this->assertSame(RewardInventoryItem::STATUS_AVAILABLE, $grant->items->first()->fresh()->status);
        $this->assertCount(0, $this->rewardQueue->payloads);
    }

    /** @return array{User,GameServer,UserGameAccount} */
    private function userWithCharacter(bool $online = false): array
    {
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
            'online' => $online,
            'clan' => null,
            'last_access' => 0,
            'play_time_seconds' => 0,
            'pvp_kills' => 0,
            'pk_kills' => 0,
            'reputation' => 0,
            'noble' => false,
            'hero' => false,
            'created_at' => null,
        ]];

        return [$user, $server, $account];
    }
}
