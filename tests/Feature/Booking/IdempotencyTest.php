<?php

namespace Tests\Feature\Booking;

class IdempotencyTest extends BookingApiTestCase
{
    public function test_retry_with_the_same_key_replays_the_original_response(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');

        $first = $this->book($class, 'ana', ['Idempotency-Key' => 'retry-1']);
        $retry = $this->book($class, 'ana', ['Idempotency-Key' => 'retry-1']);

        $first->assertCreated()->assertHeaderMissing('Idempotency-Replayed');
        $retry->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($first->json(), $retry->json());
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_retry_of_a_waitlisted_booking_keeps_the_original_position(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 1);
        $this->book($class, 'ana');
        $first = $this->book($class, 'ben', ['Idempotency-Key' => 'k']);
        $this->book($class, 'cid');

        $this->book($class, 'ben', ['Idempotency-Key' => 'k'])->assertCreated()->assertExactJson($first->json());
        $this->assertDatabaseCount('bookings', 3);
    }

    public function test_reusing_a_key_for_a_different_request_is_rejected(): void
    {
        $yoga = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');
        $spin = $this->classAt('2026-11-03T22:00:00Z', '2026-11-03T23:00:00Z');
        $this->book($yoga, 'ana', ['Idempotency-Key' => 'k'])->assertCreated();

        $this->book($spin, 'ana', ['Idempotency-Key' => 'k'])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'idempotency_key_mismatch');
        $this->assertDatabaseMissing('bookings', ['class_id' => $spin->id]);
    }

    public function test_keys_are_scoped_per_member(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');

        $ana = $this->book($class, 'ana', ['Idempotency-Key' => 'same']);
        $ben = $this->book($class, 'ben', ['Idempotency-Key' => 'same']);

        $ana->assertCreated();
        $ben->assertCreated()->assertHeaderMissing('Idempotency-Replayed');
        $this->assertNotSame($ana->json('id'), $ben->json('id'));
    }

    public function test_keys_expire_after_twenty_four_hours(): void
    {
        $class = $this->classAt('2026-11-04T22:00:00Z', '2026-11-04T23:00:00Z');
        $this->book($class, 'ana', ['Idempotency-Key' => 'k'])->assertCreated();

        $this->travel(23)->hours();
        $this->book($class, 'ana', ['Idempotency-Key' => 'k'])->assertCreated()->assertHeader('Idempotency-Replayed', 'true');

        $this->travel(2)->hours();
        $this->book($class, 'ana', ['Idempotency-Key' => 'k'])->assertConflict()->assertJsonPath('error', 'already_booked');
    }

    public function test_retry_without_a_key_never_creates_a_second_booking(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');

        $this->book($class, 'ana')->assertCreated();
        $this->book($class, 'ana')->assertConflict()->assertJsonPath('error', 'already_booked');

        $this->assertDatabaseCount('bookings', 1);
    }
}
