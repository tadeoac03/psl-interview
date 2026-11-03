<?php

namespace App\Booking\Infrastructure\Http\Resources;

use App\Booking\Application\Shared\BookingView;
use App\Booking\Domain\Model\BookingStatus;
use App\Booking\Infrastructure\Http\Iso8601;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item of GET /api/members/{memberId}/bookings[/cancelled].
 *
 * @property BookingView $resource
 */
final class MemberBookingResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{id: string, classId: string, status: string, position?: int, cancelledAt?: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'classId' => $this->resource->classId,
            'status' => $this->resource->status->value,
            ...($this->resource->status === BookingStatus::Waitlisted ? ['position' => $this->resource->position] : []),
            ...($this->resource->cancelledAt !== null ? ['cancelledAt' => Iso8601::utc($this->resource->cancelledAt)] : []),
        ];
    }
}
