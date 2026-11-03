<?php

namespace App\Booking\Infrastructure\Http\Controllers;

use App\Booking\Application\BookSeat\BookSeat;
use App\Booking\Application\CancelBooking\CancelBooking;
use App\Booking\Domain\Exception\BookingNotFound;
use App\Booking\Domain\Exception\ClassNotFound;
use App\Booking\Infrastructure\Http\Middleware\IdentifyMember;
use App\Booking\Infrastructure\Http\Resources\BookingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class BookingController
{
    public function store(Request $request, string $classId, BookSeat $bookSeat): JsonResponse
    {
        if (! Str::isUuid($classId)) {
            throw new ClassNotFound;
        }

        $booking = $bookSeat->handle($classId, IdentifyMember::member($request));

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, string $bookingId, CancelBooking $cancelBooking): Response
    {
        if (! Str::isUuid($bookingId)) {
            throw new BookingNotFound;
        }

        $cancelBooking->handle($bookingId, IdentifyMember::member($request));

        return response()->noContent();
    }
}
