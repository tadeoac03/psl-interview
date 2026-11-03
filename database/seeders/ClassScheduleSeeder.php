<?php

namespace Database\Seeders;

use App\Booking\Infrastructure\Persistence\Eloquent\FitnessClassModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo schedule around 2026-11-02 (studio local time, America/New_York).
 * Includes overlapping classes, tiny capacities to exercise the waitlist,
 * a class on the DST-change day (2026-11-01) and a cancelled class.
 */
class ClassScheduleSeeder extends Seeder
{
    private const STUDIO_TIMEZONE = 'America/New_York';

    public function run(): void
    {
        $classes = [
            ['Sunrise Yoga', '2026-11-01 07:00', 60, 10],
            ['Late Night Spin', '2026-11-01 23:30', 45, 10],
            ['Early Bird HIIT', '2026-11-02 06:00', 45, 10],
            ['After Work Spin', '2026-11-02 17:00', 45, 2],
            ['Evening Yoga', '2026-11-02 18:00', 60, 2],
            ['Pilates Core', '2026-11-02 18:30', 60, 3],
            ['Boxing Basics', '2026-11-02 19:30', 60, 1],
            ['Night Stretch', '2026-11-02 21:00', 30, 20],
            ['Morning Barre', '2026-11-03 07:00', 50, 8],
            ['Lunch HIIT', '2026-11-03 12:00', 30, 1],
        ];

        foreach ($classes as [$name, $localStart, $minutes, $capacity]) {
            $start = CarbonImmutable::parse($localStart, self::STUDIO_TIMEZONE)->utc();

            FitnessClassModel::query()->firstOrCreate(
                ['name' => $name, 'starts_at' => $start],
                ['uuid' => (string) Str::uuid7(), 'ends_at' => $start->addMinutes($minutes), 'capacity' => $capacity],
            );
        }

        $cancelledStart = CarbonImmutable::parse('2026-11-02 20:00', self::STUDIO_TIMEZONE)->utc();

        FitnessClassModel::query()->firstOrCreate(
            ['name' => 'Cancelled Zumba', 'starts_at' => $cancelledStart],
            [
                'uuid' => (string) Str::uuid7(),
                'ends_at' => $cancelledStart->addHour(),
                'capacity' => 10,
                'cancelled_at' => CarbonImmutable::parse('2026-11-02 12:00', self::STUDIO_TIMEZONE)->utc(),
            ],
        );
    }
}
