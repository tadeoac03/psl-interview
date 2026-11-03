<?php

namespace App\Booking\Infrastructure\Http\Resources;

use App\Booking\Application\Shared\BookingView;
use App\Booking\Domain\Model\BookingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Response of POST /api/classes/{id}/bookings.
 *
 * @property BookingView $resource
 */
final class BookingResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{id: string, classId: string, memberId: string, status: string, position?: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'classId' => $this->resource->classId,
            'memberId' => $this->resource->memberId,
            'status' => $this->resource->status->value,
            ...($this->resource->status === BookingStatus::Waitlisted ? ['position' => $this->resource->position] : []),
        ];
    }
}
