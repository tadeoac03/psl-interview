<?php

namespace App\Booking\Domain\Exception;

use App\Booking\Domain\Model\FitnessClass;

final class ClassCancelled extends BookingDomainException
{
    public function __construct(public readonly FitnessClass $class)
    {
        parent::__construct('The class has been cancelled.');
    }
}
