<?php

namespace App\Booking\Application\CancelBooking;

use App\Booking\Domain\Exception\BookingNotFound;
use App\Booking\Domain\Model\Booking;
use App\Booking\Domain\Model\MemberId;
use App\Booking\Domain\Port\BookingNotifier;
use App\Booking\Domain\Port\BookingRepository;
use App\Booking\Domain\Port\Clock;
use App\Booking\Domain\Port\FitnessClassRepository;
use App\Booking\Domain\Port\LockManager;
use App\Booking\Domain\Port\TransactionManager;

final readonly class CancelBooking
{
    public function __construct(
        private FitnessClassRepository $classes,
        private BookingRepository $bookings,
        private LockManager $locks,
        private TransactionManager $transactions,
        private BookingNotifier $notifier,
        private Clock $clock,
    ) {}

    /**
     * Cancels the member's booking. Cancelling an already-cancelled booking is a no-op
     * so retries are safe. Freeing a confirmed seat promotes the first waitlisted member
     * who is not confirmed in an overlapping class; members who would conflict keep their place.
     *
     * @throws BookingNotFound when the booking does not exist or belongs to another member
     */
    public function handle(string $bookingUuid, MemberId $memberId): void
    {
        $booking = $this->bookings->findByUuid($bookingUuid);

        if ($booking === null || ! $booking->isOwnedBy($memberId)) {
            throw new BookingNotFound;
        }

        /** @var array{cancelled: ?Booking, promoted: ?Booking} $changes */
        $changes = $this->transactions->run(function () use ($booking): array {
            $this->locks->lockClass($booking->classId);

            $booking = $this->bookings->findByUuid($booking->uuid);

            if ($booking->isCancelled()) {
                return ['cancelled' => null, 'promoted' => null];
            }

            $freesSeat = $booking->isConfirmed();
            $booking->cancel($this->clock->now());
            $this->bookings->save($booking);

            return ['cancelled' => $booking, 'promoted' => $freesSeat ? $this->promoteNextEligible($booking->classId) : null];
        });

        if ($changes['cancelled'] !== null) {
            $this->notifier->bookingCancelled($changes['cancelled']);
        }

        if ($changes['promoted'] !== null) {
            $this->notifier->bookingPromoted($changes['promoted']);
        }
    }

    private function promoteNextEligible(int $classId): ?Booking
    {
        $class = $this->classes->findById($classId);

        if ($class->isCancelled() || $this->bookings->countConfirmed($classId) >= $class->capacity) {
            return null;
        }

        foreach ($this->bookings->waitlistOf($classId) as $candidate) {
            $this->locks->lockMember($candidate->memberId);

            if ($this->bookings->findOverlappingConfirmedClass($candidate->memberId, $class->schedule, $classId) !== null) {
                continue;
            }

            $candidate->promote();

            return $this->bookings->save($candidate);
        }

        return null;
    }
}
