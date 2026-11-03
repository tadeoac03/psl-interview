<?php

namespace App\Booking\Infrastructure;

use App\Booking\Application\ListClasses\ClassCatalog;
use App\Booking\Application\ListMemberBookings\MemberBookingsQuery;
use App\Booking\Application\Shared\IdempotencyStore;
use App\Booking\Domain\Port\BookingNotifier;
use App\Booking\Domain\Port\BookingRepository;
use App\Booking\Domain\Port\Clock;
use App\Booking\Domain\Port\FitnessClassRepository;
use App\Booking\Domain\Port\LockManager;
use App\Booking\Domain\Port\TransactionManager;
use App\Booking\Domain\Port\UuidGenerator;
use App\Booking\Infrastructure\Persistence\DatabaseIdempotencyStore;
use App\Booking\Infrastructure\Persistence\DatabaseLockManager;
use App\Booking\Infrastructure\Persistence\DatabaseTransactionManager;
use App\Booking\Infrastructure\Persistence\Eloquent\EloquentBookingReadModel;
use App\Booking\Infrastructure\Persistence\Eloquent\EloquentBookingRepository;
use App\Booking\Infrastructure\Persistence\Eloquent\EloquentFitnessClassRepository;
use App\Booking\Infrastructure\Support\LaravelUuidGenerator;
use App\Booking\Infrastructure\Support\LogBookingNotifier;
use App\Booking\Infrastructure\Support\SystemClock;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the booking context's ports to their Laravel adapters.
 */
final class BookingServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        FitnessClassRepository::class => EloquentFitnessClassRepository::class,
        BookingRepository::class => EloquentBookingRepository::class,
        ClassCatalog::class => EloquentBookingReadModel::class,
        MemberBookingsQuery::class => EloquentBookingReadModel::class,
        LockManager::class => DatabaseLockManager::class,
        TransactionManager::class => DatabaseTransactionManager::class,
        Clock::class => SystemClock::class,
        UuidGenerator::class => LaravelUuidGenerator::class,
        BookingNotifier::class => LogBookingNotifier::class,
    ];

    public function register(): void
    {
        $this->app->singleton(IdempotencyStore::class, fn (): IdempotencyStore => new DatabaseIdempotencyStore(
            (int) config('studio.idempotency_ttl_hours'),
        ));
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
}
