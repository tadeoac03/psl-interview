<?php

namespace App\Booking\Application\ListMemberBookings;

use App\Booking\Application\Shared\BookingView;
use App\Booking\Domain\Exception\MemberAccessDenied;
use App\Booking\Domain\Model\MemberId;

final readonly class ListMemberBookings
{
    public function __construct(private MemberBookingsQuery $query) {}

    /**
     * @return list<BookingView>
     *
     * @throws MemberAccessDenied
     */
    public function active(MemberId $owner, MemberId $requester): array
    {
        $this->authorize($owner, $requester);

        return $this->query->active($owner);
    }

    /**
     * @return list<BookingView>
     *
     * @throws MemberAccessDenied
     */
    public function cancelled(MemberId $owner, MemberId $requester): array
    {
        $this->authorize($owner, $requester);

        return $this->query->cancelled($owner);
    }

    private function authorize(MemberId $owner, MemberId $requester): void
    {
        if (! $owner->equals($requester)) {
            throw new MemberAccessDenied;
        }
    }
}
