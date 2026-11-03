<?php

namespace App\Booking\Domain\Port;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
