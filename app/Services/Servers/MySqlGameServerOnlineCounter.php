<?php

namespace App\Services\Servers;

use App\Contracts\GameServerOnlineCounter;
use App\Models\GameServer;
use App\Models\LoginServer;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

final class MySqlGameServerOnlineCounter implements GameServerOnlineCounter
{
    public function __construct(
        private readonly MySqlSessionQueryTimeout $queryTimeout,
        private readonly ServerDriverRegistry $drivers,
    ) {}

    public function count(GameServer $gameServer): int
    {
        $gameServer->loadMissing('loginServer');
        $driver = $this->drivers->gameDriver((string) $gameServer->driver);
        $definition = $this->onlineCountDefinition($driver['online_count'] ?? null);
        $table = $this->identifier($definition['table']);
        $column = $this->identifier($definition['column']);
        $onlineValue = $definition['value'];

        $connection = $this->connectionValues($gameServer);
        $name = 'l2forge_online_'.Str::lower(Str::random(12));

        try {
            $database = DB::connectUsing($name, $this->configuration($connection), true);
            if (! $database instanceof Connection) {
                throw new RuntimeException('Unsupported external database connection type.');
            }

            $this->queryTimeout->apply($database);

            return (int) $database->table($table)
                ->where($column, $onlineValue)
                ->count();
        } finally {
            try {
                DB::purge($name);
            } catch (Throwable) {
                // Cleanup must not replace the monitoring result.
            }
        }
    }

    /** @return array{host:string,port:int,database:string,username:string,password:string,charset:string} */
    private function connectionValues(GameServer $gameServer): array
    {
        $loginServer = $gameServer->loginServer;
        if (! $loginServer instanceof LoginServer) {
            throw new RuntimeException('The selected GameServer has no LoginServer connection.');
        }

        if ($gameServer->use_login_server_connection) {
            return [
                'host' => $loginServer->database_host,
                'port' => $loginServer->database_port,
                'database' => $loginServer->database_name,
                'username' => $loginServer->database_username,
                'password' => $loginServer->databasePassword() ?? '',
                'charset' => $loginServer->database_charset,
            ];
        }

        return [
            'host' => trim((string) $gameServer->database_host),
            'port' => (int) $gameServer->database_port,
            'database' => trim((string) $gameServer->database_name),
            'username' => trim((string) $gameServer->database_username),
            'password' => $gameServer->databasePassword() ?? '',
            'charset' => trim((string) $gameServer->database_charset),
        ];
    }

    /**
     * @param  array{host:string,port:int,database:string,username:string,password:string,charset:string}  $values
     * @return array<string,mixed>
     */
    private function configuration(array $values): array
    {
        return [
            'driver' => 'mysql',
            'host' => $values['host'],
            'port' => $values['port'],
            'database' => $values['database'],
            'username' => $values['username'],
            'password' => $values['password'],
            'charset' => $values['charset'],
            'collation' => $this->collationFor($values['charset']),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [
                PDO::ATTR_TIMEOUT => max(1, min(30, (int) config('cms.external_database.connect_timeout_seconds', 3))),
            ],
        ];
    }

    /** @return array{table:string,column:string,value:int|string} */
    private function onlineCountDefinition(mixed $value): array
    {
        if (! is_array($value)) {
            throw new RuntimeException('The selected GameServer driver does not provide online counting.');
        }

        $table = $value['table'] ?? null;
        $column = $value['column'] ?? null;
        $onlineValue = $value['value'] ?? null;

        if (! is_string($table)
            || ! is_string($column)
            || (! is_int($onlineValue) && ! is_string($onlineValue))) {
            throw new RuntimeException('The online counter contains an invalid definition.');
        }

        return [
            'table' => $table,
            'column' => $column,
            'value' => $onlineValue,
        ];
    }

    private function identifier(mixed $value): string
    {
        if (! is_string($value) || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $value) !== 1) {
            throw new RuntimeException('The online counter contains an unsafe database identifier.');
        }

        return $value;
    }

    private function collationFor(string $charset): string
    {
        return match ($charset) {
            'utf8' => 'utf8_unicode_ci',
            'latin1' => 'latin1_swedish_ci',
            'cp1251' => 'cp1251_general_ci',
            default => 'utf8mb4_unicode_ci',
        };
    }
}
