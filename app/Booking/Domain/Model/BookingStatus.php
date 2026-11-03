<?php

namespace App\Booking\Domain\Model;

enum BookingStatus: string
{
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
}
