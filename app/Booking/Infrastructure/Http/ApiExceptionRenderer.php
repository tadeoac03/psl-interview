<?php

namespace App\Booking\Infrastructure\Http;

use App\Booking\Domain\Exception\AlreadyBooked;
use App\Booking\Domain\Exception\BookingNotFound;
use App\Booking\Domain\Exception\ClassAlreadyStarted;
use App\Booking\Domain\Exception\ClassCancelled;
use App\Booking\Domain\Exception\ClassNotFound;
use App\Booking\Domain\Exception\MemberAccessDenied;
use App\Booking\Domain\Exception\ScheduleConflict;
use App\Booking\Infrastructure\Http\Resources\ClassSummaryResource;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Maps domain and framework exceptions to the API error envelope.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof ClassNotFound => ApiError::response(404, 'class_not_found', $exception->getMessage()),
            $exception instanceof BookingNotFound => ApiError::response(404, 'booking_not_found', $exception->getMessage()),
            $exception instanceof MemberAccessDenied => ApiError::response(403, 'forbidden', $exception->getMessage()),
            $exception instanceof AlreadyBooked => ApiError::response(409, 'already_booked', $exception->getMessage()),
            $exception instanceof ScheduleConflict => ApiError::response(409, 'schedule_conflict', $exception->getMessage(), [
                'conflictsWith' => ClassSummaryResource::reference($exception->conflictsWith),
            ]),
            $exception instanceof ClassAlreadyStarted => ApiError::response(409, 'class_already_started', $exception->getMessage()),
            $exception instanceof ClassCancelled => ApiError::response(409, 'class_cancelled', $exception->getMessage()),
            $exception instanceof LockTimeoutException => ApiError::response(409, 'idempotency_key_in_use', 'A request with this Idempotency-Key is still being processed.'),
            $exception instanceof ValidationException => ApiError::response(422, 'validation_error', 'The request is invalid.', [
                'details' => $exception->errors(),
            ]),
            $exception instanceof NotFoundHttpException => ApiError::response(404, 'not_found', 'The requested resource does not exist.'),
            $exception instanceof MethodNotAllowedHttpException => ApiError::response(405, 'method_not_allowed', 'The HTTP method is not allowed for this resource.'),
            $exception instanceof HttpExceptionInterface => ApiError::response($exception->getStatusCode(), 'http_error', $exception->getMessage() ?: 'Request failed.'),
            default => config('app.debug') ? null : ApiError::response(500, 'internal_error', 'Something went wrong.'),
        };
    }
}
