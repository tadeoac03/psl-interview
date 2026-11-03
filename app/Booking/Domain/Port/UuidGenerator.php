<?php

namespace App\Booking\Domain\Port;

interface UuidGenerator
{
    public function generate(): string;
}
