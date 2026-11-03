<?php

namespace App\Booking\Domain\Exception;

final class MemberAccessDenied extends BookingDomainException
{
    public function __construct()
    {
        parent::__construct('You cannot access bookings of another member.');
    }
}
