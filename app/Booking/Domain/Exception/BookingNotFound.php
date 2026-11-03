<?php

namespace App\Booking\Domain\Exception;

final class BookingNotFound extends BookingDomainException
{
    public function __construct()
    {
        parent::__construct('The booking does not exist.');
    }
}
