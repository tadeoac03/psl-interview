<?php

namespace App\Booking\Infrastructure\Persistence;

use App\Booking\Application\Shared\IdempotencyStore;
use App\Booking\Application\Shared\StoredResponse;
use App\Booking\Infrastructure\Persistence\Eloquent\IdempotencyKeyModel;
use Illuminate\Support\Facades\Cache;

final class DatabaseIdempotencyStore implements IdempotencyStore
{
    private const LOCK_SECONDS = 10;

    private const WAIT_SECONDS = 5;

    public function __construct(private int $ttlHours) {}

    public function find(string $memberId, string $key): ?StoredResponse
    {
        $record = IdempotencyKeyModel::query()
            ->where('member_id', $memberId)
            ->where('key', $key)
            ->where('created_at', '>=', now()->subHours($this->ttlHours))
            ->first();

        return $record === null
            ? null
            : new StoredResponse($record->request_hash, $record->status_code, $record->response_body);
    }

    public function save(string $memberId, string $key, StoredResponse $response): void
    {
        IdempotencyKeyModel::query()->updateOrCreate(
            ['member_id' => $memberId, 'key' => $key],
            [
                'request_hash' => $response->requestHash,
                'status_code' => $response->statusCode,
                'response_body' => $response->body,
                'created_at' => now(),
            ],
        );
    }

    public function withLock(string $memberId, string $key, callable $callback): mixed
    {
        return Cache::lock('idempotency:'.hash('sha256', $memberId."\0".$key), self::LOCK_SECONDS)
            ->block(self::WAIT_SECONDS, $callback);
    }
}
