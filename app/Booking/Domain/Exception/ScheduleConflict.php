<?php

namespace App\Booking\Domain\Exception;

use App\Booking\Domain\Model\FitnessClass;

final class ScheduleConflict extends BookingDomainException
{
    public function __construct(public readonly FitnessClass $conflictsWith)
    {
        parent::__construct('You are already confirmed in a class that overlaps with this one.');
    }
}
