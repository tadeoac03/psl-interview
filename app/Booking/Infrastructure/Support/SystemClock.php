<?php

namespace App\Booking\Infrastructure\Support;

use App\Booking\Domain\Port\Clock;
use DateTimeImmutable;

/**
 * Backed by Laravel's now() so tests can freeze time with travelTo().
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return now('UTC')->toDateTimeImmutable();
    }
}
