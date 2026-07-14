<?php

namespace Tests\Unit;

use App\Services\Servers\MySqlExternalDatabaseConnectionTester;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MySqlExternalDatabaseConnectionTesterTest extends TestCase
{
    public function test_failed_connection_is_reported_without_cleanup_exception(): void
    {
        $database = Mockery::mock(Connection::class);
        $database->shouldReceive('getPdo')
            ->once()
            ->andThrow(new RuntimeException('Connection refused.'));

        DB::shouldReceive('connectUsing')
            ->once()
            ->with(
                Mockery::on(fn (string $name): bool => str_starts_with($name, 'l2forge_external_')),
                Mockery::on(fn (array $configuration): bool => $configuration['driver'] === 'mysql'
                    && $configuration['host'] === '127.0.0.1'
                    && $configuration['password'] === 'SecretDatabasePassword'),
                true,
            )
            ->andReturn($database);
        DB::shouldReceive('purge')
            ->once()
            ->with(Mockery::on(fn (string $name): bool => str_starts_with($name, 'l2forge_external_')));

        $report = app(MySqlExternalDatabaseConnectionTester::class)->test([
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'l2jmobiusinterlude',
            'username' => 'l2forge',
            'password' => 'SecretDatabasePassword',
            'charset' => 'utf8mb4',
        ], [], true);

        $this->assertFalse($report['connected']);
        $this->assertFalse($report['compatible']);
        $this->assertSame('connection_failed', $report['error']);
        $this->assertSame([], $report['checks']);
    }
}
