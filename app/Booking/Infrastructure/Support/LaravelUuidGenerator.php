<?php

namespace App\Booking\Infrastructure\Support;

use App\Booking\Domain\Port\UuidGenerator;
use Illuminate\Support\Str;

final class LaravelUuidGenerator implements UuidGenerator
{
    /**
     * Time-ordered UUIDv7 keeps the unique index on uuid compact.
     */
    public function generate(): string
    {
        return (string) Str::uuid7();
    }
}
