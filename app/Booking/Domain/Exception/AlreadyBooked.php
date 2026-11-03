<?php

namespace App\Booking\Domain\Exception;

final class AlreadyBooked extends BookingDomainException
{
    public function __construct()
    {
        parent::__construct('You already hold a booking for this class.');
    }
}
