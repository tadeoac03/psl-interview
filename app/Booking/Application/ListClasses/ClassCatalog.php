<?php

namespace App\Booking\Application\ListClasses;

use App\Booking\Application\Shared\ClassView;
use DateTimeImmutable;

interface ClassCatalog
{
    /**
     * Non-cancelled classes starting in [from, to), sorted by start.
     *
     * @return list<ClassView>
     */
    public function startingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array;

    public function findByUuid(string $uuid): ?ClassView;
}
