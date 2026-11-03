<?php

namespace Database\Factories;

use App\Booking\Domain\Model\BookingStatus;
use App\Booking\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Booking\Infrastructure\Persistence\Eloquent\FitnessClassModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookingModel>
 */
class BookingModelFactory extends Factory
{
    protected $model = BookingModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'class_id' => FitnessClassModel::factory(),
            'member_id' => 'member-'.fake()->unique()->numberBetween(1, 1_000_000),
            'status' => BookingStatus::Confirmed,
        ];
    }

    public function forMember(string $memberId): static
    {
        return $this->state(['member_id' => $memberId]);
    }

    public function waitlisted(): static
    {
        return $this->state(['status' => BookingStatus::Waitlisted]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => BookingStatus::Cancelled, 'cancelled_at' => CarbonImmutable::now('UTC')]);
    }
}
