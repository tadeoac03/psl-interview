<?php

namespace App\Booking\Domain\Model;

use InvalidArgumentException;

/**
 * Opaque identifier of a studio member, supplied by the client via the X-Member-Id header.
 */
final readonly class MemberId
{
    public const MAX_LENGTH = 100;

    public function __construct(public string $value)
    {
        if (trim($value) === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Member id must be a non-empty string of at most '.self::MAX_LENGTH.' characters.');
        }
    }

    public function equals(MemberId $other): bool
    {
        return $this->value === $other->value;
    }
}
