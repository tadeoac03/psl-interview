<?php

namespace Tests\Feature\Booking;

use App\Booking\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Booking\Infrastructure\Persistence\Eloquent\FitnessClassModel;
use Carbon\CarbonImmutable;

class ListClassesTest extends BookingApiTestCase
{
    public function test_returns_classes_starting_on_the_local_date_sorted_by_start(): void
    {
        $late = $this->classAt('2026-11-03T03:30:00Z', '2026-11-03T04:30:00Z', name: 'Late');      // 22:30 NY on Nov 2
        $early = $this->classAt('2026-11-02T11:00:00Z', '2026-11-02T12:00:00Z', name: 'Early');   // 06:00 NY on Nov 2
        $this->classAt('2026-11-02T04:00:00Z', '2026-11-02T05:00:00Z', name: 'PreviousDay');      // 23:00 NY on Nov 1
        $this->classAt('2026-11-03T05:00:00Z', '2026-11-03T06:00:00Z', name: 'NextDay');          // 00:00 NY on Nov 3

        $response = $this->api('GET', '/api/classes?date=2026-11-02&tz=America/New_York');

        $response->assertOk()->assertExactJson([
            [
                'id' => $early->uuid, 'name' => 'Early', 'start' => '2026-11-02T11:00:00Z', 'end' => '2026-11-02T12:00:00Z',
                'capacity' => 10, 'booked' => 0, 'waitlisted' => 0, 'seatsLeft' => 10,
            ],
            [
                'id' => $late->uuid, 'name' => 'Late', 'start' => '2026-11-03T03:30:00Z', 'end' => '2026-11-03T04:30:00Z',
                'capacity' => 10, 'booked' => 0, 'waitlisted' => 0, 'seatsLeft' => 10,
            ],
        ]);
    }

    public function test_day_boundaries_follow_the_dst_change_on_november_first(): void
    {
        // 2026-11-01 lasts 25 hours in New York: 04:00Z (00:00 EDT) until 05:00Z next day (00:00 EST).
        $firstMinute = $this->classAt('2026-11-01T04:00:00Z', '2026-11-01T05:00:00Z', name: 'Midnight EDT');
        $lastMinute = $this->classAt('2026-11-02T04:59:00Z', '2026-11-02T05:30:00Z', name: '23:59 EST');
        $this->classAt('2026-11-01T03:59:00Z', '2026-11-01T04:30:00Z', name: 'Oct 31');
        $this->classAt('2026-11-02T05:00:00Z', '2026-11-02T06:00:00Z', name: 'Nov 2');

        $response = $this->api('GET', '/api/classes?date=2026-11-01&tz=America/New_York');

        $response->assertOk();
        $this->assertSame([$firstMinute->uuid, $lastMinute->uuid], $response->json('*.id'));
    }

    public function test_time_zone_parameter_selects_the_local_day(): void
    {
        $class = $this->classAt('2026-11-02T03:00:00Z', '2026-11-02T04:00:00Z'); // Nov 1 in New York, Nov 2 in UTC

        $this->api('GET', '/api/classes?date=2026-11-02&tz=UTC')->assertOk()->assertJsonPath('0.id', $class->uuid);
        $this->api('GET', '/api/classes?date=2026-11-02&tz=America/New_York')->assertOk()->assertExactJson([]);
    }

    public function test_defaults_to_today_in_the_studio_time_zone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-03T02:00:00Z')); // still Nov 2 in New York
        $tonight = $this->classAt('2026-11-03T03:00:00Z', '2026-11-03T04:00:00Z');
        $this->classAt('2026-11-03T15:00:00Z', '2026-11-03T16:00:00Z');

        $this->api('GET', '/api/classes')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $tonight->uuid);
    }

    public function test_reports_occupancy_and_never_negative_seats(): void
    {
        $class = $this->classAt('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 2);
        BookingModel::factory()->count(2)->for($class, 'fitnessClass')->create();
        BookingModel::factory()->waitlisted()->for($class, 'fitnessClass')->create();
        BookingModel::factory()->cancelled()->for($class, 'fitnessClass')->create();

        $this->api('GET', '/api/classes?date=2026-11-02')
            ->assertOk()
            ->assertJsonPath('0.booked', 2)
            ->assertJsonPath('0.waitlisted', 1)
            ->assertJsonPath('0.seatsLeft', 0);
    }

    public function test_excludes_cancelled_classes(): void
    {
        FitnessClassModel::factory()->between('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z')->cancelled()->create();

        $this->api('GET', '/api/classes?date=2026-11-02')->assertOk()->assertExactJson([]);
    }

    public function test_rejects_invalid_date_and_time_zone(): void
    {
        $this->api('GET', '/api/classes?date=2026-02-30&tz=Mars/Olympus')
            ->assertStatus(422)
            ->assertJsonPath('error', 'validation_error')
            ->assertJsonValidationErrors(['date', 'tz'], 'details');
    }
}
