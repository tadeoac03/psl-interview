<?php

namespace App\Booking\Infrastructure\Persistence;

use App\Booking\Domain\Model\MemberId;
use App\Booking\Domain\Port\LockManager;
use App\Booking\Infrastructure\Persistence\Eloquent\FitnessClassModel;
use Illuminate\Support\Facades\DB;

/**
 * Class lock: SELECT ... FOR UPDATE on the class row (serialises seat allocation per class).
 * Member lock: transaction-scoped Postgres advisory lock (serialises a member's overlap checks
 * across different classes). SQLite serialises all writers, so it needs neither.
 */
final class DatabaseLockManager implements LockManager
{
    public function lockClass(int $classId): void
    {
        FitnessClassModel::query()->whereKey($classId)->lockForUpdate()->value('id');
    }

    public function lockMember(MemberId $memberId): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['member:'.$memberId->value]);
    }
}
