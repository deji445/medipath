<?php

namespace Tests\Unit\Support;

use App\Support\RecoverableDatabaseTransaction;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Mockery;
use PDOException;
use Tests\TestCase;

class RecoverableDatabaseTransactionTest extends TestCase
{
    public function test_it_uses_a_clean_connection_for_the_transaction(): void
    {
        config()->set('database.default', 'pgsql');
        config()->set('database.connections.pgsql.driver', 'pgsql');
        $database = Mockery::mock(DatabaseManager::class);
        $connection = Mockery::mock(ConnectionInterface::class);

        $database->shouldReceive('purge')->once()->withNoArgs();
        $database->shouldReceive('connection')->once()->withNoArgs()->andReturn($connection);
        $connection->shouldReceive('transaction')->once()->andReturnUsing(
            fn (callable $callback): mixed => $callback(),
        );

        $result = (new RecoverableDatabaseTransaction($database))->run(fn (): string => 'saved');

        $this->assertSame('saved', $result);
    }

    public function test_it_reconnects_and_retries_an_aborted_postgres_transaction_once(): void
    {
        config()->set('database.default', 'pgsql');
        config()->set('database.connections.pgsql.driver', 'pgsql');
        $database = Mockery::mock(DatabaseManager::class);
        $failedConnection = Mockery::mock(ConnectionInterface::class);
        $cleanConnection = Mockery::mock(ConnectionInterface::class);
        $previous = new PDOException('Current transaction is aborted.');
        $previous->errorInfo = ['25P02', 7, 'Current transaction is aborted.'];
        $exception = new QueryException('pgsql', 'update patient_conditions set is_active = false', [], $previous);

        $database->shouldReceive('purge')->twice()->withNoArgs();
        $database->shouldReceive('connection')->twice()->withNoArgs()
            ->andReturn($failedConnection, $cleanConnection);
        $failedConnection->shouldReceive('transaction')->once()->andThrow($exception);
        $cleanConnection->shouldReceive('transaction')->once()->andReturnUsing(
            fn (callable $callback): mixed => $callback(),
        );

        $result = (new RecoverableDatabaseTransaction($database))->run(fn (): string => 'saved');

        $this->assertSame('saved', $result);
    }

    public function test_it_does_not_retry_an_unrelated_database_error(): void
    {
        config()->set('database.default', 'pgsql');
        config()->set('database.connections.pgsql.driver', 'pgsql');
        $database = Mockery::mock(DatabaseManager::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $previous = new PDOException('A constraint failed.');
        $previous->errorInfo = ['23514', 7, 'A constraint failed.'];
        $exception = new QueryException('pgsql', 'insert into visits', [], $previous);

        $database->shouldReceive('purge')->once()->withNoArgs();
        $database->shouldReceive('connection')->once()->withNoArgs()->andReturn($connection);
        $connection->shouldReceive('transaction')->once()->andThrow($exception);

        $this->expectExceptionObject($exception);

        (new RecoverableDatabaseTransaction($database))->run(fn (): string => 'not reached');
    }

    public function test_it_does_not_purge_a_non_postgres_connection(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.driver', 'sqlite');
        $database = Mockery::mock(DatabaseManager::class);
        $connection = Mockery::mock(ConnectionInterface::class);

        $database->shouldNotReceive('purge');
        $database->shouldReceive('connection')->once()->withNoArgs()->andReturn($connection);
        $connection->shouldReceive('transaction')->once()->andReturnUsing(
            fn (callable $callback): mixed => $callback(),
        );

        $result = (new RecoverableDatabaseTransaction($database))->run(fn (): string => 'saved');

        $this->assertSame('saved', $result);
    }
}
