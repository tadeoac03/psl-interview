<?php

namespace App\Booking\Application\BookSeat;

use App\Booking\Application\Shared\BookingView;
use App\Booking\Domain\Exception\AlreadyBooked;
use App\Booking\Domain\Exception\ClassAlreadyStarted;
use App\Booking\Domain\Exception\ClassCancelled;
use App\Booking\Domain\Exception\ClassNotFound;
use App\Booking\Domain\Exception\ScheduleConflict;
use App\Booking\Domain\Model\Booking;
use App\Booking\Domain\Model\MemberId;
use App\Booking\Domain\Port\BookingRepository;
use App\Booking\Domain\Port\Clock;
use App\Booking\Domain\Port\FitnessClassRepository;
use App\Booking\Domain\Port\LockManager;
use App\Booking\Domain\Port\TransactionManager;
use App\Booking\Domain\Port\UuidGenerator;

final readonly class BookSeat
{
    public function __construct(
        private FitnessClassRepository $classes,
        private BookingRepository $bookings,
        private LockManager $locks,
        private TransactionManager $transactions,
        private UuidGenerator $uuids,
        private Clock $clock,
    ) {}

    /**
     * Confirms a seat when one is free, otherwise joins the waitlist.
     * The schedule-overlap rule applies only to confirmed seats, so a member may
     * wait for a class that overlaps one they are confirmed in.
     *
     * @throws ClassNotFound
     * @throws ClassCancelled
     * @throws ClassAlreadyStarted
     * @throws AlreadyBooked
     * @throws ScheduleConflict
     */
    public function handle(string $classUuid, MemberId $memberId): BookingView
    {
        $class = $this->classes->findByUuid($classUuid) ?? throw new ClassNotFound;

        return $this->transactions->run(function () use ($class, $memberId): BookingView {
            $this->locks->lockClass($class->id);
            $this->locks->lockMember($memberId);

            $class->assertBookableAt($this->clock->now());

            if ($this->bookings->findActive($class->id, $memberId) !== null) {
                throw new AlreadyBooked;
            }

            if ($this->bookings->countConfirmed($class->id) < $class->capacity) {
                $conflict = $this->bookings->findOverlappingConfirmedClass($memberId, $class->schedule, $class->id);

                if ($conflict !== null) {
                    throw new ScheduleConflict($conflict);
                }

                $booking = $this->bookings->save(Booking::confirmed($this->uuids->generate(), $class, $memberId));

                return new BookingView($booking->uuid, $class->uuid, $memberId->value, $booking->status());
            }

            $booking = $this->bookings->save(Booking::waitlisted($this->uuids->generate(), $class, $memberId));

            return new BookingView(
                $booking->uuid,
                $class->uuid,
                $memberId->value,
                $booking->status(),
                $this->bookings->waitlistPosition($booking),
            );
        });
    }
}
