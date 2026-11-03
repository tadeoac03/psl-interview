<?php

namespace App\Booking\Domain\Model;

use DateTimeImmutable;
use LogicException;

final class Booking
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly int $classId,
        public readonly MemberId $memberId,
        private BookingStatus $status,
        private ?DateTimeImmutable $cancelledAt = null,
    ) {}

    public static function confirmed(string $uuid, FitnessClass $class, MemberId $memberId): self
    {
        return new self(null, $uuid, $class->id, $memberId, BookingStatus::Confirmed);
    }

    public static function waitlisted(string $uuid, FitnessClass $class, MemberId $memberId): self
    {
        return new self(null, $uuid, $class->id, $memberId, BookingStatus::Waitlisted);
    }

    public function status(): BookingStatus
    {
        return $this->status;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function isOwnedBy(MemberId $memberId): bool
    {
        return $this->memberId->equals($memberId);
    }

    public function isCancelled(): bool
    {
        return $this->status === BookingStatus::Cancelled;
    }

    public function isConfirmed(): bool
    {
        return $this->status === BookingStatus::Confirmed;
    }

    public function cancel(DateTimeImmutable $now): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->status = BookingStatus::Cancelled;
        $this->cancelledAt = $now;
    }

    public function promote(): void
    {
        if ($this->status !== BookingStatus::Waitlisted) {
            throw new LogicException('Only waitlisted bookings can be promoted.');
        }

        $this->status = BookingStatus::Confirmed;
    }
}
