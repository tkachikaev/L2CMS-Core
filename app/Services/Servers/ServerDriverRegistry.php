<?php

namespace App\Services\Servers;

final class ServerDriverRegistry
{
    /**
     * @return array<string, array{
     *     label:string,
     *     description:string,
     *     ready:bool,
     *     requirements:list<array{table:string,columns:list<string>,required:bool}>
     * }>
     */
    public function loginDrivers(): array
    {
        return [
            'l2j_mobius' => [
                'label' => __('L2J Mobius — Interlude and newer'),
                'description' => __('L2J Mobius LoginServer for Interlude and newer builds.'),
                'ready' => true,
                'requirements' => [
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
                ],
            ],
            'l2j_mobius_legacy' => [
                'label' => __('L2J Mobius Legacy — C1/C4'),
                'description' => __('L2J Mobius legacy LoginServer for C1 and C4 builds.'),
                'ready' => true,
                'requirements' => [
                    [
                        'table' => 'accounts',
                        'columns' => ['login', 'password', 'email', 'created_time', 'lastactive', 'accessLevel', 'lastIP', 'lastServer'],
                        'required' => true,
                    ],
                ],
            ],
            'rusacis' => [
                'label' => 'RUSaCis',
                'description' => __('RUSaCis driver placeholder. Schema support will be added later.'),
                'ready' => false,
                'requirements' => [],
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     label:string,
     *     description:string,
     *     ready:bool,
     *     character_created_at_column?:string|null,
     *     requirements:list<array{table:string,columns:list<string>,required:bool}>
     * }>
     */
    public function gameDrivers(): array
    {
        return [
            'l2j_mobius_ct0_interlude' => [
                'label' => 'L2J Mobius — CT0 Interlude',
                'description' => __('L2J Mobius CT0 Interlude character and account game data tables.'),
                'ready' => true,
                'character_created_at_column' => 'createDate',
                'requirements' => [
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
                ],
            ],
            'rusacis' => [
                'label' => 'RUSaCis',
                'description' => __('RUSaCis driver placeholder. Schema support will be added later.'),
                'ready' => false,
                'requirements' => [],
            ],
        ];
    }

    /** @return list<string> */
    public function loginDriverKeys(): array
    {
        return array_keys($this->loginDrivers());
    }

    /** @return list<string> */
    public function gameDriverKeys(): array
    {
        return array_keys($this->gameDrivers());
    }

    /** @return array{label:string,description:string,ready:bool,requirements:list<array{table:string,columns:list<string>,required:bool}>}|null */
    public function loginDriver(string $key): ?array
    {
        return $this->loginDrivers()[$key] ?? null;
    }

    /** @return array{label:string,description:string,ready:bool,character_created_at_column?:string|null,requirements:list<array{table:string,columns:list<string>,required:bool}>}|null */
    public function gameDriver(string $key): ?array
    {
        return $this->gameDrivers()[$key] ?? null;
    }
}
