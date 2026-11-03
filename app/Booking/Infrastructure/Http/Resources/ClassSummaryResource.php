<?php

namespace App\Booking\Infrastructure\Http\Resources;

use App\Booking\Application\Shared\ClassView;
use App\Booking\Domain\Model\FitnessClass;
use App\Booking\Infrastructure\Http\Iso8601;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ClassView $resource
 */
final class ClassSummaryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{id: string, name: string, start: string, end: string, capacity: int, booked: int, waitlisted: int, seatsLeft: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'start' => Iso8601::utc($this->resource->start),
            'end' => Iso8601::utc($this->resource->end),
            'capacity' => $this->resource->capacity,
            'booked' => $this->resource->booked,
            'waitlisted' => $this->resource->waitlisted,
            'seatsLeft' => $this->resource->seatsLeft(),
        ];
    }

    /**
     * Compact representation used when pointing at another class (e.g. conflictsWith).
     *
     * @return array{id: string, name: string, start: string, end: string}
     */
    public static function reference(FitnessClass $class): array
    {
        return [
            'id' => $class->uuid,
            'name' => $class->name,
            'start' => Iso8601::utc($class->schedule->start),
            'end' => Iso8601::utc($class->schedule->end),
        ];
    }
}
