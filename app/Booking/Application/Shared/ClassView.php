<?php

namespace App\Booking\Application\Shared;

use DateTimeImmutable;

final readonly class ClassView
{
    public function __construct(
        public string $id,
        public string $name,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public int $capacity,
        public int $booked,
        public int $waitlisted,
    ) {}

    public function seatsLeft(): int
    {
        return max(0, $this->capacity - $this->booked);
    }
}
