<?php

namespace Tests\Feature\Booking;

class CreateClassTest extends BookingApiTestCase
{
    public function test_creates_a_class(): void
    {
        $response = $this->api('POST', '/api/classes', ['X-Member-Id' => 'admin'], [
            'name' => 'Spin', 'start' => '2026-11-05T22:00:00Z', 'end' => '2026-11-05T22:45:00Z', 'capacity' => 12,
        ]);

        $response->assertCreated()->assertJson([
            'name' => 'Spin', 'start' => '2026-11-05T22:00:00Z', 'end' => '2026-11-05T22:45:00Z',
            'capacity' => 12, 'booked' => 0, 'waitlisted' => 0, 'seatsLeft' => 12,
        ]);
        $this->assertDatabaseHas('classes', ['uuid' => $response->json('id'), 'capacity' => 12]);
    }

    public function test_validates_the_payload(): void
    {
        $this->api('POST', '/api/classes', ['X-Member-Id' => 'admin'], [
            'name' => '', 'start' => '2026-11-05 17:00', 'end' => '2026-11-05T21:00:00Z', 'capacity' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'start', 'capacity'], 'details');

        $this->api('POST', '/api/classes', ['X-Member-Id' => 'admin'], [
            'name' => 'Spin', 'start' => '2026-11-05T22:00:00Z', 'end' => '2026-11-05T21:00:00Z', 'capacity' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors(['end'], 'details');

        $this->assertDatabaseCount('classes', 0);
    }

    public function test_requires_the_member_header(): void
    {
        $this->api('POST', '/api/classes', [], ['name' => 'Spin'])->assertUnauthorized();
    }
}
