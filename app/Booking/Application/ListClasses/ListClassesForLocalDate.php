<?php

namespace App\Booking\Application\ListClasses;

use App\Booking\Application\Shared\ClassView;
use App\Booking\Domain\Port\Clock;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ListClassesForLocalDate
{
    public function __construct(private ClassCatalog $catalog, private Clock $clock) {}

    /**
     * Classes that start on the given local calendar date in the given time zone.
     * Day boundaries are computed in local time, so DST days of 23/25 hours are honoured.
     *
     * @param  string|null  $date  Y-m-d; defaults to today in $timeZone
     * @return list<ClassView>
     */
    public function handle(?string $date, DateTimeZone $timeZone): array
    {
        $date ??= $this->clock->now()->setTimezone($timeZone)->format('Y-m-d');

        $startOfDay = new DateTimeImmutable($date.' 00:00:00', $timeZone);
        $startOfNextDay = $startOfDay->modify('+1 day');

        return $this->catalog->startingBetween($startOfDay, $startOfNextDay);
    }
}
