<?php

namespace App\Booking\Domain\Port;

use App\Booking\Domain\Model\Booking;

/**
 * Lets members know about changes they did not trigger in this request (or want a receipt for).
 */
interface BookingNotifier
{
    public function bookingCancelled(Booking $booking): void;

    public function bookingPromoted(Booking $booking): void;
}
