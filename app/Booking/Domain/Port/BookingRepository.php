<?php

namespace App\Booking\Domain\Port;

use App\Booking\Domain\Exception\AlreadyBooked;
use App\Booking\Domain\Model\Booking;
use App\Booking\Domain\Model\FitnessClass;
use App\Booking\Domain\Model\MemberId;
use App\Booking\Domain\Model\TimeRange;

interface BookingRepository
{
    public function findByUuid(string $uuid): ?Booking;

    public function findActive(int $classId, MemberId $memberId): ?Booking;

    public function countConfirmed(int $classId): int;

    /**
     * 1-based position of a waitlisted booking in its class queue (FIFO).
     */
    public function waitlistPosition(Booking $booking): int;

    /**
     * @return list<Booking> waitlisted bookings of the class, first in line first
     */
    public function waitlistOf(int $classId): array;

    /**
     * First non-cancelled class, other than $excludedClassId, where the member is
     * confirmed and whose schedule overlaps the given range.
     */
    public function findOverlappingConfirmedClass(MemberId $memberId, TimeRange $range, int $excludedClassId): ?FitnessClass;

    /**
     * @throws AlreadyBooked when the member already holds an active booking for the class
     */
    public function save(Booking $booking): Booking;
}
