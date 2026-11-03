<?php

namespace Tests\Feature\Booking;

use App\Booking\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Booking\Infrastructure\Persistence\Eloquent\FitnessClassModel;

class BookSeatTest extends BookingApiTestCase
{
    public function test_confirms_a_seat_when_capacity_is_available(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 1);

        $response = $this->book($class, 'ana');

        $response->assertCreated()->assertExactJson([
            'id' => $response->json('id'),
            'classId' => $class->uuid,
            'memberId' => 'ana',
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('bookings', ['uuid' => $response->json('id'), 'member_id' => 'ana', 'status' => 'confirmed']);
    }

    public function test_joins_the_waitlist_with_its_position_when_the_class_is_full(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 1);
        $this->book($class, 'ana')->assertJsonPath('status', 'confirmed');

        $this->book($class, 'ben')->assertCreated()->assertJsonPath('status', 'waitlisted')->assertJsonPath('position', 1);
        $this->book($class, 'cid')->assertCreated()->assertJsonPath('status', 'waitlisted')->assertJsonPath('position', 2);
    }

    public function test_requires_the_member_header(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');

        $this->api('POST', "/api/classes/{$class->uuid}/bookings")
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'unauthenticated', 'message' => 'A valid X-Member-Id header is required.']);

        $this->api('POST', "/api/classes/{$class->uuid}/bookings", ['X-Member-Id' => '   '])->assertUnauthorized();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_unknown_class_is_not_found(): void
    {
        $this->api('POST', '/api/classes/01a19d99-0000-7000-8000-000000000000/bookings', ['X-Member-Id' => 'ana'])
            ->assertNotFound()
            ->assertJsonPath('error', 'class_not_found');

        $this->api('POST', '/api/classes/not-a-uuid/bookings', ['X-Member-Id' => 'ana'])
            ->assertNotFound()
            ->assertJsonPath('error', 'class_not_found');
    }

    public function test_member_cannot_book_the_same_class_twice(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 1);
        $this->book($class, 'ana')->assertCreated();
        $this->book($class, 'ben')->assertCreated();

        $this->book($class, 'ana')->assertConflict()->assertJsonPath('error', 'already_booked');
        $this->book($class, 'ben')->assertConflict()->assertJsonPath('error', 'already_booked');
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_member_can_book_again_after_cancelling(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');
        $first = $this->book($class, 'ana')->json('id');
        $this->cancel($first, 'ana')->assertNoContent();

        $second = $this->book($class, 'ana');

        $second->assertCreated()->assertJsonPath('status', 'confirmed');
        $this->assertNotSame($first, $second->json('id'));
    }

    public function test_rejects_a_seat_overlapping_a_confirmed_class(): void
    {
        $yoga = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', name: 'Yoga');
        $spin = $this->classAt('2026-11-02T22:30:00Z', '2026-11-02T23:30:00Z', name: 'Spin');
        $this->book($yoga, 'ana')->assertCreated();

        $this->book($spin, 'ana')->assertConflict()->assertJson([
            'error' => 'schedule_conflict',
            'conflictsWith' => ['id' => $yoga->uuid, 'name' => 'Yoga', 'start' => '2026-11-02T22:00:00Z', 'end' => '2026-11-02T23:00:00Z'],
        ]);
        $this->assertDatabaseMissing('bookings', ['class_id' => $spin->id]);
    }

    public function test_back_to_back_classes_do_not_conflict(): void
    {
        $first = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');
        $second = $this->classAt('2026-11-02T23:00:00Z', '2026-11-03T00:00:00Z');
        $this->book($first, 'ana')->assertCreated();

        $this->book($second, 'ana')->assertCreated()->assertJsonPath('status', 'confirmed');
    }

    public function test_overlap_ignores_waitlisted_and_cancelled_bookings(): void
    {
        $waitlistedIn = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');
        $cancelledIn = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');
        BookingModel::factory()->waitlisted()->forMember('ana')->for($waitlistedIn, 'fitnessClass')->create();
        BookingModel::factory()->cancelled()->forMember('ana')->for($cancelledIn, 'fitnessClass')->create();
        $target = $this->classAt('2026-11-02T22:30:00Z', '2026-11-02T23:30:00Z');

        $this->book($target, 'ana')->assertCreated()->assertJsonPath('status', 'confirmed');
    }

    public function test_member_may_join_the_waitlist_of_an_overlapping_class(): void
    {
        $confirmed = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');
        $full = $this->classAt('2026-11-02T22:30:00Z', '2026-11-02T23:30:00Z', capacity: 1);
        $this->book($confirmed, 'ana')->assertCreated();
        $this->book($full, 'ben')->assertCreated();

        $this->book($full, 'ana')->assertCreated()->assertJsonPath('status', 'waitlisted')->assertJsonPath('position', 1);
    }

    public function test_cannot_book_a_class_that_already_started(): void
    {
        $started = $this->classAt('2026-11-02T12:00:00Z', '2026-11-02T13:00:00Z'); // starts exactly "now"

        $this->book($started, 'ana')->assertConflict()->assertJsonPath('error', 'class_already_started');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_cannot_book_a_cancelled_class(): void
    {
        $class = FitnessClassModel::factory()->between('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z')->cancelled()->create();

        $this->book($class, 'ana')->assertConflict()->assertJsonPath('error', 'class_cancelled');
    }
}
