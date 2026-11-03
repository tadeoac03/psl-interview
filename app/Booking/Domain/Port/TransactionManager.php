<?php

namespace App\Booking\Domain\Port;

interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
