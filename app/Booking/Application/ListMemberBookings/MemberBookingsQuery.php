<?php

namespace App\Booking\Application\ListMemberBookings;

use App\Booking\Application\Shared\BookingView;
use App\Booking\Domain\Model\MemberId;

interface MemberBookingsQuery
{
    /**
     * Confirmed and waitlisted bookings, oldest first. Waitlisted ones carry their position.
     *
     * @return list<BookingView>
     */
    public function active(MemberId $memberId): array;

    /**
     * Cancelled bookings, most recently cancelled first.
     *
     * @return list<BookingView>
     */
    public function cancelled(MemberId $memberId): array;
}
