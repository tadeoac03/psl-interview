<?php

namespace App\Booking\Infrastructure\Persistence\Eloquent;

use App\Booking\Domain\Model\FitnessClass;
use App\Booking\Domain\Model\TimeRange;
use App\Booking\Domain\Port\FitnessClassRepository;
use Illuminate\Support\Carbon;

final class EloquentFitnessClassRepository implements FitnessClassRepository
{
    public function findByUuid(string $uuid): ?FitnessClass
    {
        $model = FitnessClassModel::query()->where('uuid', $uuid)->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function findById(int $id): ?FitnessClass
    {
        $model = FitnessClassModel::query()->find($id);

        return $model === null ? null : self::toDomain($model);
    }

    public function save(FitnessClass $class): FitnessClass
    {
        $model = $class->id === null ? new FitnessClassModel : FitnessClassModel::query()->findOrFail($class->id);

        $model->fill([
            'uuid' => $class->uuid,
            'name' => $class->name,
            'starts_at' => Carbon::instance($class->schedule->start)->utc(),
            'ends_at' => Carbon::instance($class->schedule->end)->utc(),
            'capacity' => $class->capacity,
            'cancelled_at' => $class->cancelledAt === null ? null : Carbon::instance($class->cancelledAt)->utc(),
        ])->save();

        return self::toDomain($model);
    }

    public static function toDomain(FitnessClassModel $model): FitnessClass
    {
        return new FitnessClass(
            $model->id,
            $model->uuid,
            $model->name,
            new TimeRange($model->starts_at->toDateTimeImmutable(), $model->ends_at->toDateTimeImmutable()),
            $model->capacity,
            $model->cancelled_at?->toDateTimeImmutable(),
        );
    }
}
