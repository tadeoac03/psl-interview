<?php

namespace App\Booking\Domain\Exception;

final class ClassNotFound extends BookingDomainException
{
    public function __construct()
    {
        parent::__construct('The class does not exist.');
    }
}
