<?php

namespace App\Booking\Application\Shared;

use App\Booking\Domain\Model\BookingStatus;
use DateTimeImmutable;

final readonly class BookingView
{
    public function __construct(
        public string $id,
        public string $classId,
        public string $memberId,
        public BookingStatus $status,
        public ?int $position = null,
        public ?DateTimeImmutable $cancelledAt = null,
    ) {}
}
