<?php

namespace App\Booking\Domain\Model;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Half-open interval [start, end): back-to-back ranges do not overlap.
 */
final readonly class TimeRange
{
    public function __construct(public DateTimeImmutable $start, public DateTimeImmutable $end)
    {
        if ($end <= $start) {
            throw new InvalidArgumentException('Time range end must be after its start.');
        }
    }

    public function overlaps(TimeRange $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function hasStartedAt(DateTimeImmutable $moment): bool
    {
        return $this->start <= $moment;
    }
}
