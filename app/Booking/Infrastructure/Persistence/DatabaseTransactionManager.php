<?php

namespace App\Booking\Infrastructure\Persistence;

use App\Booking\Domain\Port\TransactionManager;
use Illuminate\Support\Facades\DB;

final class DatabaseTransactionManager implements TransactionManager
{
    /**
     * Deadlocks and serialization failures are retried by Laravel up to the given attempts.
     */
    private const ATTEMPTS = 3;

    public function run(callable $callback): mixed
    {
        return DB::transaction($callback, self::ATTEMPTS);
    }
}
