<?php

namespace App\Booking\Domain\Model;

use App\Booking\Domain\Exception\ClassAlreadyStarted;
use App\Booking\Domain\Exception\ClassCancelled;
use DateTimeImmutable;
use InvalidArgumentException;

final class FitnessClass
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $uuid,
        public readonly string $name,
        public readonly TimeRange $schedule,
        public readonly int $capacity,
        public readonly ?DateTimeImmutable $cancelledAt = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Class name is required.');
        }

        if ($capacity < 1) {
            throw new InvalidArgumentException('Class capacity must be at least 1.');
        }
    }

    public static function schedule(string $uuid, string $name, TimeRange $schedule, int $capacity): self
    {
        return new self(null, $uuid, $name, $schedule, $capacity);
    }

    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    /**
     * @throws ClassCancelled
     * @throws ClassAlreadyStarted
     */
    public function assertBookableAt(DateTimeImmutable $now): void
    {
        if ($this->isCancelled()) {
            throw new ClassCancelled($this);
        }

        if ($this->schedule->hasStartedAt($now)) {
            throw new ClassAlreadyStarted($this);
        }
    }
}
