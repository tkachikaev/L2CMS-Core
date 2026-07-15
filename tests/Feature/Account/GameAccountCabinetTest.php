<?php

namespace Tests\Feature\Account;

use App\Contracts\GameAccountGateway;
use App\Models\AuditLog;
use App\Models\GameServer;
use App\Models\LoginServer;
use App\Models\User;
use App\Models\UserGameAccount;
use App\Services\GameAccountSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Fakes\FakeGameAccountGateway;
use Tests\TestCase;

class GameAccountCabinetTest extends TestCase
{
    use RefreshDatabase;

    private FakeGameAccountGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeGameAccountGateway;
        $this->app->instance(GameAccountGateway::class, $this->gateway);
    }

    public function test_player_account_routes_require_authentication(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
        $this->get('/account/game-accounts/create')->assertRedirect(route('login'));
    }

    public function test_player_sees_a_separate_minimal_account_dashboard(): void
    {
        $user = $this->user();
        [$loginServer, $gameServer] = $this->servers();
        UserGameAccount::query()->create([
            'user_id' => $user->id,
            'login_server_id' => $loginServer->id,
            'registration_game_server_id' => $gameServer->id,
            'game_login' => 'PlayerOne',
            'normalized_login' => 'playerone',
        ]);

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('Личный кабинет игрока')
            ->assertSee('PlayerOne')
            ->assertSee('Interlude x10')
            ->assertDontSee('Панель управления');
    }

    public function test_player_can_create_a_mobius_account_from_a_selected_game_server(): void
    {
        $user = $this->user();
        [, $gameServer] = $this->servers();

        $this->actingAs($user)->post('/account/game-accounts', [
            'game_server_id' => $gameServer->id,
            'game_login' => 'Player01',
            'game_password' => 'StrongPass1',
            'game_password_confirmation' => 'StrongPass1',
        ])->assertRedirect();

        $link = UserGameAccount::query()->firstOrFail();
        $this->assertSame($user->id, $link->user_id);
        $this->assertSame('Player01', $link->game_login);
        $this->assertSame('player01', $link->normalized_login);
        $this->assertSame($gameServer->id, $link->registration_game_server_id);
        $this->assertSame('StrongPass1', $this->gateway->created[0]['password']);
    }

    public function test_localized_creation_redirect_and_account_actions_resolve_route_parameters(): void
    {
        $user = $this->user();
        [, $gameServer] = $this->servers();

        $response = $this->actingAs($user)->post('/ru/account/game-accounts', [
            'game_server_id' => $gameServer->id,
            'game_login' => 'Localized01',
            'game_password' => 'StrongPass1',
            'game_password_confirmation' => 'StrongPass1',
        ]);

        $account = UserGameAccount::query()->firstOrFail();

        $response->assertRedirect(route('localized.game-accounts.show', [
            'locale' => 'ru',
            'gameAccount' => $account,
        ]));

        $this->actingAs($user)
            ->get('/ru/account/game-accounts/'.$account->id)
            ->assertOk()
            ->assertSee('Localized01');

        $this->actingAs($user)->put('/ru/account/game-accounts/'.$account->id.'/password', [
            'current_password' => 'Password123',
            'game_password' => 'NewStrong1',
            'game_password_confirmation' => 'NewStrong1',
        ])->assertSessionHas('status');

        $this->assertSame('NewStrong1', $this->gateway->passwordChanges[0]['password']);
    }

    public function test_disabled_game_account_creation_is_rejected(): void
    {
        $user = $this->user();
        [, $gameServer] = $this->servers();
        $this->settings(['enabled' => false]);

        $this->actingAs($user)->post('/account/game-accounts', [
            'game_server_id' => $gameServer->id,
            'game_login' => 'Disabled01',
            'game_password' => 'StrongPass1',
            'game_password_confirmation' => 'StrongPass1',
        ])->assertSessionHasErrors('game_login');

        $this->assertDatabaseCount('user_game_accounts', 0);
        $this->assertSame([], $this->gateway->created);
    }

    public function test_existing_login_server_account_cannot_be_created_again(): void
    {
        $user = $this->user();
        [$loginServer, $gameServer] = $this->servers();
        $this->gateway->existing[$loginServer->id.':player01'] = true;

        $this->actingAs($user)->post('/account/game-accounts', [
            'game_server_id' => $gameServer->id,
            'game_login' => 'Player01',
            'game_password' => 'StrongPass1',
            'game_password_confirmation' => 'StrongPass1',
        ])->assertSessionHasErrors('game_login');

        $this->assertDatabaseCount('user_game_accounts', 0);
    }

    public function test_external_creation_failure_rolls_back_the_local_link(): void
    {
        $user = $this->user();
        [, $gameServer] = $this->servers();
        $this->gateway->failCreate = true;

        $this->actingAs($user)->post('/account/game-accounts', [
            'game_server_id' => $gameServer->id,
            'game_login' => 'Failed01',
            'game_password' => 'StrongPass1',
            'game_password_confirmation' => 'StrongPass1',
        ])->assertSessionHasErrors('game_login')
            ->assertSessionMissingInput(['game_password', 'game_password_confirmation']);

        $this->assertDatabaseCount('user_game_accounts', 0);
        $audit = AuditLog::query()->where('action', 'user.game_account_creation_failed')->firstOrFail();
        $this->assertStringNotContainsString('StrongPass1', json_encode($audit->details, JSON_THROW_ON_ERROR));
    }

    public function test_game_login_and_password_follow_the_admin_policy(): void
    {
        $user = $this->user();
        [, $gameServer] = $this->servers();
        app(GameAccountSettings::class)->update([
            'enabled' => true,
            'max_accounts' => 10,
            'login_min' => 6,
            'login_max' => 12,
            'login_lower' => true,
            'login_upper' => true,
            'login_digit' => true,
            'password_min' => 10,
            'password_max' => 20,
            'password_lower' => true,
            'password_upper' => true,
            'password_digit' => true,
        ]);

        $this->actingAs($user)->post('/account/game-accounts', [
            'game_server_id' => $gameServer->id,
            'game_login' => 'player',
            'game_password' => 'weakpass',
            'game_password_confirmation' => 'weakpass',
        ])->assertSessionHasErrors(['game_login', 'game_password'])
            ->assertSessionMissingInput(['game_password', 'game_password_confirmation']);

        $this->assertDatabaseCount('user_game_accounts', 0);
        $this->assertSame([], $this->gateway->created);
    }

    public function test_game_password_rejects_non_ascii_and_special_characters(): void
    {
        $user = $this->user();
        [, $gameServer] = $this->servers();

        foreach (['StrongПароль1', 'StrongPass!1', 'Strong Pass1'] as $password) {
            $this->actingAs($user)->post('/account/game-accounts', [
                'game_server_id' => $gameServer->id,
                'game_login' => 'Player01',
                'game_password' => $password,
                'game_password_confirmation' => $password,
            ])->assertSessionHasErrors('game_password')
                ->assertSessionMissingInput(['game_password', 'game_password_confirmation']);
        }

        $this->assertDatabaseCount('user_game_accounts', 0);
        $this->assertSame([], $this->gateway->created);
    }

    public function test_global_account_limit_is_enforced(): void
    {
        $user = $this->user();
        [$loginServer, $gameServer] = $this->servers();
        app(GameAccountSettings::class)->update([
            'enabled' => true,
            'max_accounts' => 1,
            'login_min' => 4,
            'login_max' => 16,
            'login_lower' => false,
            'login_upper' => false,
            'login_digit' => false,
            'password_min' => 8,
            'password_max' => 32,
            'password_lower' => true,
            'password_upper' => true,
            'password_digit' => true,
        ]);
        UserGameAccount::query()->create([
            'user_id' => $user->id,
            'login_server_id' => $loginServer->id,
            'registration_game_server_id' => $gameServer->id,
            'game_login' => 'Existing01',
            'normalized_login' => 'existing01',
        ]);

        $this->actingAs($user)->post('/account/game-accounts', [
            'game_server_id' => $gameServer->id,
            'game_login' => 'Second01',
            'game_password' => 'StrongPass1',
            'game_password_confirmation' => 'StrongPass1',
        ])->assertSessionHasErrors('game_login');

        $this->assertDatabaseCount('user_game_accounts', 1);
    }

    public function test_player_cannot_open_another_users_game_account(): void
    {
        $owner = $this->user('owner@example.com', 'owner');
        $intruder = $this->user('intruder@example.com', 'intruder');
        [$loginServer, $gameServer] = $this->servers();
        $account = UserGameAccount::query()->create([
            'user_id' => $owner->id,
            'login_server_id' => $loginServer->id,
            'registration_game_server_id' => $gameServer->id,
            'game_login' => 'Private01',
            'normalized_login' => 'private01',
        ]);

        $this->actingAs($intruder)
            ->get('/account/game-accounts/'.$account->id)
            ->assertNotFound();
    }

    public function test_account_details_show_characters_grouped_by_game_server(): void
    {
        $user = $this->user();
        [$loginServer, $gameServer] = $this->servers();
        $account = UserGameAccount::query()->create([
            'user_id' => $user->id,
            'login_server_id' => $loginServer->id,
            'registration_game_server_id' => $gameServer->id,
            'game_login' => 'Hero01',
            'normalized_login' => 'hero01',
        ]);
        $this->gateway->charactersByServer[$gameServer->id] = [[
            'id' => 100,
            'name' => 'Bubi',
            'level' => 78,
            'class_id' => 88,
            'online' => true,
            'clan' => 'L2Forge',
            'last_access' => 0,
        ]];

        $this->actingAs($user)
            ->get('/account/game-accounts/'.$account->id)
            ->assertOk()
            ->assertSee('Interlude x10')
            ->assertSee('Bubi')
            ->assertSee('Дуэлист')
            ->assertSee('L2Forge')
            ->assertSee('В игре');
    }

    public function test_character_query_failure_does_not_break_the_account_page(): void
    {
        $user = $this->user();
        [$loginServer, $gameServer] = $this->servers();
        $account = UserGameAccount::query()->create([
            'user_id' => $user->id,
            'login_server_id' => $loginServer->id,
            'registration_game_server_id' => $gameServer->id,
            'game_login' => 'Timeout01',
            'normalized_login' => 'timeout01',
        ]);
        $this->gateway->failCharacters = true;

        $this->actingAs($user)
            ->get('/account/game-accounts/'.$account->id)
            ->assertOk()
            ->assertSee('Interlude x10')
            ->assertSee('Данные персонажей временно недоступны.');
    }

    public function test_player_cannot_change_another_users_game_account_password(): void
    {
        $owner = $this->user('owner@example.com', 'owner');
        $intruder = $this->user('intruder@example.com', 'intruder');
        [$loginServer, $gameServer] = $this->servers();
        $account = UserGameAccount::query()->create([
            'user_id' => $owner->id,
            'login_server_id' => $loginServer->id,
            'registration_game_server_id' => $gameServer->id,
            'game_login' => 'PrivatePass01',
            'normalized_login' => 'privatepass01',
        ]);

        $this->actingAs($intruder)->put('/account/game-accounts/'.$account->id.'/password', [
            'current_password' => 'Password123',
            'game_password' => 'NewStrong1',
            'game_password_confirmation' => 'NewStrong1',
        ])->assertNotFound();

        $this->assertSame([], $this->gateway->passwordChanges);
    }

    public function test_player_cannot_change_game_password_to_unsupported_characters(): void
    {
        $user = $this->user();
        [$loginServer, $gameServer] = $this->servers();
        $account = UserGameAccount::query()->create([
            'user_id' => $user->id,
            'login_server_id' => $loginServer->id,
            'registration_game_server_id' => $gameServer->id,
            'game_login' => 'Password01',
            'normalized_login' => 'password01',
        ]);

        $this->actingAs($user)->put('/account/game-accounts/'.$account->id.'/password', [
            'current_password' => 'Password123',
            'game_password' => 'NewStrong!1',
            'game_password_confirmation' => 'NewStrong!1',
        ])->assertSessionHasErrors('game_password')
            ->assertSessionMissingInput(['game_password', 'game_password_confirmation']);

        $this->assertSame([], $this->gateway->passwordChanges);
    }

    public function test_player_can_change_game_password_only_after_confirming_cms_password(): void
    {
        $user = $this->user();
        [$loginServer, $gameServer] = $this->servers();
        $account = UserGameAccount::query()->create([
            'user_id' => $user->id,
            'login_server_id' => $loginServer->id,
            'registration_game_server_id' => $gameServer->id,
            'game_login' => 'Password01',
            'normalized_login' => 'password01',
        ]);

        $this->actingAs($user)->put('/account/game-accounts/'.$account->id.'/password', [
            'current_password' => 'WrongPassword',
            'game_password' => 'NewStrong1',
            'game_password_confirmation' => 'NewStrong1',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame([], $this->gateway->passwordChanges);

        $this->actingAs($user)->put('/account/game-accounts/'.$account->id.'/password', [
            'current_password' => 'Password123',
            'game_password' => 'NewStrong1',
            'game_password_confirmation' => 'NewStrong1',
        ])->assertSessionHas('status');
        $this->assertSame('NewStrong1', $this->gateway->passwordChanges[0]['password']);
    }

    /** @param array<string,mixed> $overrides */
    private function settings(array $overrides = []): void
    {
        app(GameAccountSettings::class)->update(array_replace([
            'enabled' => true,
            'max_accounts' => 10,
            'login_min' => 4,
            'login_max' => 16,
            'login_lower' => false,
            'login_upper' => false,
            'login_digit' => false,
            'password_min' => 8,
            'password_max' => 32,
            'password_lower' => true,
            'password_upper' => true,
            'password_digit' => true,
        ], $overrides));
    }

    private function user(string $email = 'player@example.com', string $name = 'player'): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('Password123'),
        ]);

        $user->markEmailAsVerified();

        return $user->refresh();
    }

    /** @return array{LoginServer,GameServer} */
    private function servers(): array
    {
        $loginServer = LoginServer::query()->create([
            'name' => 'Main LoginServer',
            'driver' => 'l2j_mobius',
            'database_host' => '127.0.0.1',
            'database_port' => 3306,
            'database_name' => 'l2j',
            'database_username' => 'cms',
            'database_password' => 'secret',
            'database_charset' => 'utf8',
        ]);
        $gameServer = GameServer::query()->create([
            'name' => 'Interlude x10',
            'rates' => 'x10',
            'chronicle' => 'Interlude',
            'mode' => 'PvP',
            'sort_order' => 1,
            'login_server_id' => $loginServer->id,
            'driver' => 'l2j_mobius_ct0_interlude',
            'use_login_server_connection' => true,
        ]);

        return [$loginServer, $gameServer];
    }
}
