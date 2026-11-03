<?php

namespace App\Booking\Application\Shared;

interface IdempotencyStore
{
    /**
     * Stored response for the key if it was recorded within the retention window.
     */
    public function find(string $memberId, string $key): ?StoredResponse;

    public function save(string $memberId, string $key, StoredResponse $response): void;

    /**
     * Runs the callback while holding an exclusive lock on the key so concurrent
     * retries are processed one after another.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withLock(string $memberId, string $key, callable $callback): mixed;
}
