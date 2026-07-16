<?php

namespace Tests\Unit;

use App\Services\Servers\ServerDriverRegistry;
use Tests\TestCase;

class ServerDriverRegistryTest extends TestCase
{
    public function test_modern_mobius_login_driver_keeps_its_identifier_and_schema_contract(): void
    {
        $driver = app(ServerDriverRegistry::class)->loginDriver('l2j_mobius');

        $this->assertNotNull($driver);
        $this->assertSame(__('L2J Mobius — Interlude and newer'), $driver['label']);
        $this->assertTrue($driver['ready']);
        $this->assertSame([
            [
                'table' => 'accounts',
                'columns' => ['login', 'password', 'email', 'created_time', 'lastactive', 'accessLevel', 'lastIP', 'lastServer'],
                'required' => true,
            ],
            [
                'table' => 'account_data',
                'columns' => ['account_name', 'var', 'value'],
                'required' => false,
            ],
            [
                'table' => 'accounts_ipauth',
                'columns' => ['login', 'ip', 'type'],
                'required' => false,
            ],
        ], $driver['requirements']);
    }

    public function test_legacy_mobius_login_driver_requires_only_the_accounts_table(): void
    {
        $driver = app(ServerDriverRegistry::class)->loginDriver('l2j_mobius_legacy');

        $this->assertNotNull($driver);
        $this->assertSame(__('L2J Mobius Legacy — C1/C4'), $driver['label']);
        $this->assertTrue($driver['ready']);
        $this->assertSame([
            [
                'table' => 'accounts',
                'columns' => ['login', 'password', 'email', 'created_time', 'lastactive', 'accessLevel', 'lastIP', 'lastServer'],
                'required' => true,
            ],
        ], $driver['requirements']);
    }

    public function test_both_mobius_login_driver_identifiers_are_registered(): void
    {
        $keys = app(ServerDriverRegistry::class)->loginDriverKeys();

        $this->assertContains('l2j_mobius', $keys);
        $this->assertContains('l2j_mobius_legacy', $keys);
    }

    public function test_mobius_interlude_game_driver_matches_schema_contract(): void
    {
        $driver = app(ServerDriverRegistry::class)->gameDriver('l2j_mobius_ct0_interlude');

        $this->assertNotNull($driver);
        $this->assertTrue($driver['ready']);
        $this->assertSame('createDate', $driver['character_created_at_column']);
        $this->assertSame([
            [
                'table' => 'characters',
                'columns' => [
                    'account_name',
                    'charId',
                    'char_name',
                    'level',
                    'classid',
                    'online',
                    'accesslevel',
                    'pvpkills',
                    'pkkills',
                    'clanid',
                    'lastAccess',
                    'createDate',
                ],
                'required' => true,
            ],
            [
                'table' => 'account_gsdata',
                'columns' => ['account_name', 'var', 'value'],
                'required' => false,
            ],
            [
                'table' => 'account_premium',
                'columns' => ['account_name', 'enddate'],
                'required' => false,
            ],
        ], $driver['requirements']);
    }

    public function test_rusacis_drivers_are_registered_as_placeholders(): void
    {
        $registry = app(ServerDriverRegistry::class);
        $loginDriver = $registry->loginDriver('rusacis');
        $gameDriver = $registry->gameDriver('rusacis');

        $this->assertNotNull($loginDriver);
        $this->assertFalse($loginDriver['ready']);
        $this->assertSame([], $loginDriver['requirements']);

        $this->assertNotNull($gameDriver);
        $this->assertFalse($gameDriver['ready']);
        $this->assertSame([], $gameDriver['requirements']);
    }
}
