<?php

namespace App\Support;

use Closure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

final readonly class RecoverableDatabaseTransaction
{
    public function __construct(private DatabaseManager $database) {}

    /**
     * Run a critical write on a clean connection and retry once when PostgreSQL
     * reports that a previously reused transaction is already aborted.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(Closure $callback): mixed
    {
        if (! $this->usesPostgres()) {
            return $this->database->connection()->transaction($callback);
        }

        $this->database->purge();

        try {
            return $this->database->connection()->transaction($callback);
        } catch (QueryException $exception) {
            if (! $this->isAbortedPostgresTransaction($exception)) {
                throw $exception;
            }

            $this->database->purge();

            return $this->database->connection()->transaction($callback);
        }
    }

    private function isAbortedPostgresTransaction(QueryException $exception): bool
    {
        return $exception->getCode() === '25P02'
            || ($exception->errorInfo[0] ?? null) === '25P02';
    }

    private function usesPostgres(): bool
    {
        $defaultConnection = (string) config('database.default');

        return config("database.connections.{$defaultConnection}.driver") === 'pgsql';
    }
}
