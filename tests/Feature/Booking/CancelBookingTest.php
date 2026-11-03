<?php

namespace Tests\Feature\Booking;

use App\Booking\Domain\Model\Booking;
use App\Booking\Domain\Port\BookingNotifier;
use App\Booking\Infrastructure\Persistence\Eloquent\BookingModel;

class CancelBookingTest extends BookingApiTestCase
{
    public function test_cancelling_a_confirmed_seat_promotes_the_first_waitlisted_member(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 1);
        $ana = $this->book($class, 'ana')->json('id');
        $ben = $this->book($class, 'ben')->json('id');
        $cid = $this->book($class, 'cid')->json('id');

        $this->cancel($ana, 'ana')->assertNoContent();

        $this->assertDatabaseHas('bookings', ['uuid' => $ana, 'status' => 'cancelled']);
        $this->assertDatabaseHas('bookings', ['uuid' => $ben, 'status' => 'confirmed']);
        $this->memberBookings('cid')->assertExactJson([['id' => $cid, 'classId' => $class->uuid, 'status' => 'waitlisted', 'position' => 1]]);
    }

    public function test_promotion_skips_members_confirmed_in_an_overlapping_class(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 1);
        $overlapping = $this->classAt('2026-11-02T22:30:00Z', '2026-11-02T23:30:00Z');
        $ana = $this->book($class, 'ana')->json('id');
        $ben = $this->book($class, 'ben')->json('id');
        $cid = $this->book($class, 'cid')->json('id');
        $this->book($overlapping, 'ben')->assertCreated()->assertJsonPath('status', 'confirmed');

        $this->cancel($ana, 'ana')->assertNoContent();

        $this->assertDatabaseHas('bookings', ['uuid' => $cid, 'status' => 'confirmed']);
        $this->memberBookings('ben')->assertJsonFragment(['id' => $ben, 'status' => 'waitlisted', 'position' => 1]);
    }

    public function test_cancelling_a_waitlisted_booking_does_not_promote_anyone_and_shifts_positions(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 1);
        $this->book($class, 'ana');
        $ben = $this->book($class, 'ben')->json('id');
        $cid = $this->book($class, 'cid')->json('id');

        $this->cancel($ben, 'ben')->assertNoContent();

        $this->assertSame(1, BookingModel::query()->where('status', 'confirmed')->count());
        $this->memberBookings('cid')->assertExactJson([['id' => $cid, 'classId' => $class->uuid, 'status' => 'waitlisted', 'position' => 1]]);
    }

    public function test_cancelling_twice_is_idempotent_and_promotes_only_once(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 1);
        $ana = $this->book($class, 'ana')->json('id');
        $this->book($class, 'ben');
        $cid = $this->book($class, 'cid')->json('id');

        $this->cancel($ana, 'ana')->assertNoContent();
        $this->cancel($ana, 'ana')->assertNoContent();

        $this->assertDatabaseHas('bookings', ['uuid' => $cid, 'status' => 'waitlisted']);
        $this->assertSame(1, BookingModel::query()->where('status', 'confirmed')->count());
    }

    public function test_bookings_of_other_members_are_not_found(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');
        $ana = $this->book($class, 'ana')->json('id');

        $this->cancel($ana, 'ben')->assertNotFound()->assertJsonPath('error', 'booking_not_found');

        $this->assertDatabaseHas('bookings', ['uuid' => $ana, 'status' => 'confirmed']);
    }

    public function test_unknown_booking_is_not_found(): void
    {
        $this->cancel('01a19d99-0000-7000-8000-000000000000', 'ana')->assertNotFound()->assertJsonPath('error', 'booking_not_found');
        $this->cancel('nope', 'ana')->assertNotFound()->assertJsonPath('error', 'booking_not_found');
    }

    public function test_requires_the_member_header(): void
    {
        $booking = BookingModel::factory()->create();

        $this->api('DELETE', "/api/bookings/{$booking->uuid}")->assertUnauthorized()->assertJsonPath('error', 'unauthenticated');
        $this->assertDatabaseHas('bookings', ['uuid' => $booking->uuid, 'status' => 'confirmed']);
    }

    public function test_notifies_the_cancellation_and_the_promotion(): void
    {
        $notifier = new class implements BookingNotifier
        {
            /** @var list<string> */
            public array $events = [];

            public function bookingCancelled(Booking $booking): void
            {
                $this->events[] = "cancelled:{$booking->memberId->value}";
            }

            public function bookingPromoted(Booking $booking): void
            {
                $this->events[] = "promoted:{$booking->memberId->value}";
            }
        };
        $this->app->instance(BookingNotifier::class, $notifier);
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 1);
        $ana = $this->book($class, 'ana')->json('id');
        $this->book($class, 'ben');

        $this->cancel($ana, 'ana');
        $this->cancel($ana, 'ana');

        $this->assertSame(['cancelled:ana', 'promoted:ben'], $notifier->events);
    }
}
