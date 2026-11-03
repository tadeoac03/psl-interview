<?php

namespace App\Booking\Infrastructure\Persistence\Eloquent;

use App\Booking\Domain\Exception\AlreadyBooked;
use App\Booking\Domain\Model\Booking;
use App\Booking\Domain\Model\BookingStatus;
use App\Booking\Domain\Model\FitnessClass;
use App\Booking\Domain\Model\MemberId;
use App\Booking\Domain\Model\TimeRange;
use App\Booking\Domain\Port\BookingRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

final class EloquentBookingRepository implements BookingRepository
{
    public function findByUuid(string $uuid): ?Booking
    {
        $model = BookingModel::query()->where('uuid', $uuid)->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function findActive(int $classId, MemberId $memberId): ?Booking
    {
        $model = BookingModel::query()
            ->where('class_id', $classId)
            ->where('member_id', $memberId->value)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->first();

        return $model === null ? null : self::toDomain($model);
    }

    public function countConfirmed(int $classId): int
    {
        return BookingModel::query()
            ->where('class_id', $classId)
            ->where('status', BookingStatus::Confirmed)
            ->count();
    }

    public function waitlistPosition(Booking $booking): int
    {
        return BookingModel::query()
            ->where('class_id', $booking->classId)
            ->where('status', BookingStatus::Waitlisted)
            ->where('id', '<=', $booking->id)
            ->count();
    }

    public function waitlistOf(int $classId): array
    {
        return BookingModel::query()
            ->where('class_id', $classId)
            ->where('status', BookingStatus::Waitlisted)
            ->orderBy('id')
            ->get()
            ->map(fn (BookingModel $model): Booking => self::toDomain($model))
            ->all();
    }

    public function findOverlappingConfirmedClass(MemberId $memberId, TimeRange $range, int $excludedClassId): ?FitnessClass
    {
        $model = FitnessClassModel::query()
            ->whereKeyNot($excludedClassId)
            ->whereNull('cancelled_at')
            ->where('starts_at', '<', Carbon::instance($range->end)->utc())
            ->where('ends_at', '>', Carbon::instance($range->start)->utc())
            ->whereHas('bookings', fn ($bookings) => $bookings
                ->where('member_id', $memberId->value)
                ->where('status', BookingStatus::Confirmed))
            ->orderBy('starts_at')
            ->first();

        return $model === null ? null : EloquentFitnessClassRepository::toDomain($model);
    }

    public function save(Booking $booking): Booking
    {
        $model = $booking->id === null ? new BookingModel : BookingModel::query()->findOrFail($booking->id);

        $model->fill([
            'uuid' => $booking->uuid,
            'class_id' => $booking->classId,
            'member_id' => $booking->memberId->value,
            'status' => $booking->status(),
            'cancelled_at' => $booking->cancelledAt() === null ? null : Carbon::instance($booking->cancelledAt())->utc(),
        ]);

        try {
            $model->save();
        } catch (UniqueConstraintViolationException) {
            throw new AlreadyBooked;
        }

        return self::toDomain($model);
    }

    public static function toDomain(BookingModel $model): Booking
    {
        return new Booking(
            $model->id,
            $model->uuid,
            $model->class_id,
            new MemberId($model->member_id),
            $model->status,
            $model->cancelled_at?->toDateTimeImmutable(),
        );
    }
}
