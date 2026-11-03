<?php

namespace App\Booking\Infrastructure\Http\Middleware;

use App\Booking\Application\Shared\IdempotencyStore;
use App\Booking\Application\Shared\StoredResponse;
use App\Booking\Infrastructure\Http\ApiError;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replays the stored response when a POST is retried with the same Idempotency-Key.
 * Keys are scoped per member; reusing a key for a different request is rejected.
 * Server errors (5xx) are not stored so the client can retry them.
 * Must run after IdentifyMember.
 */
final class EnsureIdempotency
{
    public const HEADER = 'Idempotency-Key';

    public const REPLAY_HEADER = 'Idempotency-Replayed';

    private const MAX_KEY_LENGTH = 255;

    public function __construct(private IdempotencyStore $store) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! $request->isMethod('POST') || $key === null || $key === '') {
            return $next($request);
        }

        if (mb_strlen($key) > self::MAX_KEY_LENGTH) {
            return ApiError::response(422, 'validation_error', 'The request is invalid.', [
                'details' => [self::HEADER => ['The Idempotency-Key may not be greater than '.self::MAX_KEY_LENGTH.' characters.']],
            ]);
        }

        $memberId = IdentifyMember::member($request)->value;
        $requestHash = hash('sha256', $request->method().' '.$request->path()."\n".json_encode($request->json()->all()));

        return $this->store->withLock($memberId, $key, function () use ($request, $next, $memberId, $key, $requestHash): Response {
            $stored = $this->store->find($memberId, $key);

            if ($stored !== null) {
                if (! hash_equals($stored->requestHash, $requestHash)) {
                    return ApiError::response(422, 'idempotency_key_mismatch', 'This Idempotency-Key was already used for a different request.');
                }

                return new JsonResponse($stored->body, $stored->statusCode, [self::REPLAY_HEADER => 'true']);
            }

            $response = $next($request);

            if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
                $this->store->save($memberId, $key, new StoredResponse($requestHash, $response->getStatusCode(), $response->getData(true)));
            }

            return $response;
        });
    }
}
