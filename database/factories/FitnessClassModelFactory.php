<?php

namespace Database\Factories;

use App\Booking\Infrastructure\Persistence\Eloquent\FitnessClassModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FitnessClassModel>
 */
class FitnessClassModelFactory extends Factory
{
    protected $model = FitnessClassModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = CarbonImmutable::now('UTC')->addDays(fake()->numberBetween(1, 14))->setTime(fake()->numberBetween(11, 23), 0);

        return [
            'uuid' => (string) Str::uuid7(),
            'name' => fake()->randomElement(['Yoga', 'Spin', 'Pilates', 'HIIT', 'Boxing', 'Barre']).' '.fake()->word(),
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
            'capacity' => fake()->numberBetween(5, 20),
        ];
    }

    /**
     * Schedules the class for the given UTC interval.
     */
    public function between(string $startUtc, string $endUtc): static
    {
        return $this->state([
            'starts_at' => CarbonImmutable::parse($startUtc, 'UTC'),
            'ends_at' => CarbonImmutable::parse($endUtc, 'UTC'),
        ]);
    }

    public function capacity(int $capacity): static
    {
        return $this->state(['capacity' => $capacity]);
    }

    public function cancelled(): static
    {
        return $this->state(['cancelled_at' => CarbonImmutable::now('UTC')]);
    }
}
