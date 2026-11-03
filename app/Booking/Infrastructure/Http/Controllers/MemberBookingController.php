<?php

namespace App\Booking\Infrastructure\Http\Controllers;

use App\Booking\Application\ListMemberBookings\ListMemberBookings;
use App\Booking\Domain\Exception\MemberAccessDenied;
use App\Booking\Domain\Model\MemberId;
use App\Booking\Infrastructure\Http\Middleware\IdentifyMember;
use App\Booking\Infrastructure\Http\Resources\MemberBookingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

final class MemberBookingController
{
    public function index(Request $request, string $memberId, ListMemberBookings $listBookings): AnonymousResourceCollection
    {
        return MemberBookingResource::collection(
            $listBookings->active($this->owner($memberId), IdentifyMember::member($request)),
        );
    }

    public function cancelled(Request $request, string $memberId, ListMemberBookings $listBookings): AnonymousResourceCollection
    {
        return MemberBookingResource::collection(
            $listBookings->cancelled($this->owner($memberId), IdentifyMember::member($request)),
        );
    }

    private function owner(string $memberId): MemberId
    {
        try {
            return new MemberId($memberId);
        } catch (InvalidArgumentException) {
            throw new MemberAccessDenied;
        }
    }
}
