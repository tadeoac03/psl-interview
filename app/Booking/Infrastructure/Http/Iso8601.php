<?php

namespace App\Booking\Infrastructure\Http;

use DateTimeImmutable;
use DateTimeZone;

final class Iso8601
{
    public const FORMAT = 'Y-m-d\TH:i:s\Z';

    public static function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }
}
