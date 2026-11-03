<?php

namespace App\Booking\Infrastructure\Http;

use Illuminate\Http\JsonResponse;

/**
 * Single error envelope for every non-2xx API response, so clients can always branch on `error`:
 * { "error": "<machine_code>", "message": "<human readable>", ...context }
 */
final class ApiError
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function response(int $status, string $code, string $message, array $context = []): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message, ...$context], $status);
    }
}
