<?php

namespace App\Booking\Infrastructure\Http\Middleware;

use App\Booking\Domain\Model\MemberId;
use App\Booking\Infrastructure\Http\ApiError;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires the X-Member-Id header and exposes it as the `member` request attribute.
 */
final class IdentifyMember
{
    public const HEADER = 'X-Member-Id';

    public const ATTRIBUTE = 'member';

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $member = new MemberId(trim((string) $request->header(self::HEADER)));
        } catch (InvalidArgumentException) {
            return ApiError::response(401, 'unauthenticated', 'A valid X-Member-Id header is required.');
        }

        $request->attributes->set(self::ATTRIBUTE, $member);

        return $next($request);
    }

    public static function member(Request $request): MemberId
    {
        return $request->attributes->get(self::ATTRIBUTE);
    }
}
