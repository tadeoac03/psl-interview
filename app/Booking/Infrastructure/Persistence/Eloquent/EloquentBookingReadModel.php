<?php

namespace App\Booking\Infrastructure\Persistence\Eloquent;

use App\Booking\Application\ListClasses\ClassCatalog;
use App\Booking\Application\ListMemberBookings\MemberBookingsQuery;
use App\Booking\Application\Shared\BookingView;
use App\Booking\Application\Shared\ClassView;
use App\Booking\Domain\Model\BookingStatus;
use App\Booking\Domain\Model\MemberId;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Query-side adapter: builds the views the API returns in as few queries as possible.
 */
final class EloquentBookingReadModel implements ClassCatalog, MemberBookingsQuery
{
    public function startingBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return $this->classesWithOccupancy()
            ->whereNull('cancelled_at')
            ->where('starts_at', '>=', Carbon::instance($from)->utc())
            ->where('starts_at', '<', Carbon::instance($to)->utc())
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(fn (FitnessClassModel $model): ClassView => self::toClassView($model))
            ->all();
    }

    public function findByUuid(string $uuid): ?ClassView
    {
        $model = $this->classesWithOccupancy()->where('uuid', $uuid)->first();

        return $model === null ? null : self::toClassView($model);
    }

    public function active(MemberId $memberId): array
    {
        return BookingModel::query()
            ->with('fitnessClass:id,uuid')
            ->select('bookings.*')
            ->selectSub(
                BookingModel::query()
                    ->from('bookings', 'ahead')
                    ->selectRaw('count(*)')
                    ->whereColumn('ahead.class_id', 'bookings.class_id')
                    ->where('ahead.status', BookingStatus::Waitlisted)
                    ->whereColumn('ahead.id', '<=', 'bookings.id'),
                'waitlist_position',
            )
            ->where('member_id', $memberId->value)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Waitlisted])
            ->orderBy('id')
            ->get()
            ->map(fn (BookingModel $model): BookingView => new BookingView(
                $model->uuid,
                $model->fitnessClass->uuid,
                $model->member_id,
                $model->status,
                $model->status === BookingStatus::Waitlisted ? (int) $model->getAttribute('waitlist_position') : null,
            ))
            ->all();
    }

    public function cancelled(MemberId $memberId): array
    {
        return BookingModel::query()
            ->with('fitnessClass:id,uuid')
            ->where('member_id', $memberId->value)
            ->where('status', BookingStatus::Cancelled)
            ->orderByDesc('cancelled_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BookingModel $model): BookingView => new BookingView(
                $model->uuid,
                $model->fitnessClass->uuid,
                $model->member_id,
                $model->status,
                cancelledAt: $model->cancelled_at?->toDateTimeImmutable(),
            ))
            ->all();
    }

    /**
     * @return Builder<FitnessClassModel>
     */
    private function classesWithOccupancy(): Builder
    {
        return FitnessClassModel::query()->withCount([
            'bookings as booked_count' => fn ($bookings) => $bookings->where('status', BookingStatus::Confirmed),
            'bookings as waitlisted_count' => fn ($bookings) => $bookings->where('status', BookingStatus::Waitlisted),
        ]);
    }

    private static function toClassView(FitnessClassModel $model): ClassView
    {
        return new ClassView(
            $model->uuid,
            $model->name,
            $model->starts_at->toDateTimeImmutable(),
            $model->ends_at->toDateTimeImmutable(),
            $model->capacity,
            (int) $model->getAttribute('booked_count'),
            (int) $model->getAttribute('waitlisted_count'),
        );
    }
}
