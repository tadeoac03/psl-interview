<?php

namespace Tests\Feature\Booking;

class MemberBookingsTest extends BookingApiTestCase
{
    public function test_lists_active_bookings_with_waitlist_position(): void
    {
        $open = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');
        $full = $this->classAt('2026-11-03T22:00:00Z', '2026-11-03T23:00:00Z', capacity: 1);
        $gone = $this->classAt('2026-11-04T22:00:00Z', '2026-11-04T23:00:00Z');
        $this->book($full, 'ben');
        $confirmed = $this->book($open, 'ana')->json('id');
        $waitlisted = $this->book($full, 'ana')->json('id');
        $this->cancel($this->book($gone, 'ana')->json('id'), 'ana');

        $this->memberBookings('ana')->assertOk()->assertExactJson([
            ['id' => $confirmed, 'classId' => $open->uuid, 'status' => 'confirmed'],
            ['id' => $waitlisted, 'classId' => $full->uuid, 'status' => 'waitlisted', 'position' => 1],
        ]);
    }

    public function test_lists_cancelled_bookings_separately(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');
        $booking = $this->book($class, 'ana')->json('id');
        $this->cancel($booking, 'ana');

        $this->api('GET', '/api/members/ana/bookings/cancelled', ['X-Member-Id' => 'ana'])
            ->assertOk()
            ->assertExactJson([['id' => $booking, 'classId' => $class->uuid, 'status' => 'cancelled', 'cancelledAt' => '2026-11-02T12:00:00Z']]);
    }

    public function test_members_cannot_read_bookings_of_others(): void
    {
        $this->api('GET', '/api/members/ana/bookings', ['X-Member-Id' => 'ben'])->assertForbidden()->assertJsonPath('error', 'forbidden');
        $this->api('GET', '/api/members/ana/bookings/cancelled', ['X-Member-Id' => 'ben'])->assertForbidden();
    }

    public function test_requires_the_member_header(): void
    {
        $this->api('GET', '/api/members/ana/bookings')->assertUnauthorized()->assertJsonPath('error', 'unauthenticated');
    }

    public function test_cancelled_bookings_require_the_member_header(): void
    {
        $this->api('GET', '/api/members/ana/bookings/cancelled')->assertUnauthorized()->assertJsonPath('error', 'unauthenticated');
    }
}
