<?php

namespace App\Services\Servers;

use App\Models\GameServer;
use App\Models\LoginServer;

final class ServerAuditValues
{
    /** @return array<string, mixed> */
    public function loginServer(LoginServer $server): array
    {
        return [
            'name' => $server->name,
            'driver' => $server->driver,
            'database_host' => $server->database_host,
            'database_port' => $server->database_port,
            'database_name' => $server->database_name,
            'database_username' => $server->database_username,
            'database_charset' => $server->database_charset,
            'service_host' => $server->service_host,
            'service_port' => $server->service_port,
            'database_password_saved' => $server->hasDatabasePassword(),
        ];
    }

    /** @return array<string, mixed> */
    public function gameServer(GameServer $server): array
    {
        return [
            'name' => $server->name,
            'rates' => $server->rates,
            'chronicle' => $server->chronicle,
            'mode' => $server->mode,
            'maintenance_enabled' => (bool) $server->maintenance_enabled,
            'login_server_id' => $server->login_server_id,
            'driver' => $server->driver,
            'use_login_server_connection' => $server->use_login_server_connection,
            'database_host' => $server->database_host,
            'database_port' => $server->database_port,
            'database_name' => $server->database_name,
            'database_username' => $server->database_username,
            'database_charset' => $server->database_charset,
            'database_password_saved' => $server->hasDatabasePassword(),
            'service_host' => $server->service_host,
            'service_port' => $server->service_port,
        ];
    }
}
