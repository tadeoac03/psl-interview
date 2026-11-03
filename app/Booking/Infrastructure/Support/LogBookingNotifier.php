<?php

namespace App\Booking\Infrastructure\Support;

use App\Booking\Domain\Model\Booking;
use App\Booking\Domain\Port\BookingNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder adapter until a real channel (mail, push) is chosen.
 */
final class LogBookingNotifier implements BookingNotifier
{
    public function bookingCancelled(Booking $booking): void
    {
        Log::info('booking.cancelled', ['booking' => $booking->uuid, 'member' => $booking->memberId->value]);
    }

    public function bookingPromoted(Booking $booking): void
    {
        Log::info('booking.promoted', ['booking' => $booking->uuid, 'member' => $booking->memberId->value]);
    }
}
