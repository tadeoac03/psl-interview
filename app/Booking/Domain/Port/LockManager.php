<?php

namespace App\Booking\Domain\Port;

use App\Booking\Domain\Model\MemberId;

/**
 * Pessimistic locks held until the surrounding transaction ends.
 * Always acquire the class lock before any member lock to avoid deadlocks.
 */
interface LockManager
{
    public function lockClass(int $classId): void;

    public function lockMember(MemberId $memberId): void;
}
