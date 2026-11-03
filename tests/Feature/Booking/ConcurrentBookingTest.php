<?php

namespace Tests\Feature\Booking;

use App\Booking\Application\BookSeat\BookSeat;
use App\Booking\Domain\Model\MemberId;
use App\Booking\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Booking\Infrastructure\Persistence\Eloquent\FitnessClassModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;
use Throwable;

/**
 * Runs bookings in parallel OS processes, each with its own Postgres connection,
 * to prove the locking strategy holds under real concurrency.
 */
#[Group('concurrency')]
#[RequiresPhpExtension('pcntl')]
class ConcurrentBookingTest extends TestCase
{
    use DatabaseTruncation;

    private string $resultsDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Concurrency guarantees are verified on Postgres.');
        }

        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', 'UTC'));
        $this->resultsDirectory = sys_get_temp_dir().'/booking-race-'.uniqid();
        mkdir($this->resultsDirectory);
    }

    protected function tearDown(): void
    {
        if (! isset($this->resultsDirectory)) {
            parent::tearDown();

            return;
        }

        array_map('unlink', glob($this->resultsDirectory.'/*'));
        rmdir($this->resultsDirectory);

        /** Rows here are really committed; leave nothing behind for transactional tests that follow. */
        DB::statement('TRUNCATE bookings, classes, idempotency_keys RESTART IDENTITY CASCADE');

        parent::tearDown();
    }

    public function test_only_one_member_gets_the_last_seat(): void
    {
        $class = $this->createClass('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z', capacity: 1);

        $results = $this->race(array_map(
            fn (int $i): array => [$class->uuid, "member-{$i}"],
            range(1, 8),
        ));

        $this->assertSame(1, count(array_keys($results, 'confirmed', true)));
        $this->assertSame(7, count(array_keys($results, 'waitlisted', true)));
        $this->assertSame(1, BookingModel::query()->where('status', 'confirmed')->count());
    }

    public function test_a_member_cannot_be_confirmed_in_two_overlapping_classes_at_once(): void
    {
        $yoga = $this->createClass('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');
        $spin = $this->createClass('2026-11-02T22:30:00Z', '2026-11-02T23:30:00Z');

        $results = $this->race([[$yoga->uuid, 'ana'], [$spin->uuid, 'ana'], [$yoga->uuid, 'ana'], [$spin->uuid, 'ana']]);

        $this->assertSame(1, BookingModel::query()->where('member_id', 'ana')->where('status', 'confirmed')->count());
        $this->assertSame(1, count(array_keys($results, 'confirmed', true)));
        $this->assertEmpty(array_diff($results, ['confirmed', 'ScheduleConflict', 'AlreadyBooked']));
    }

    public function test_simultaneous_duplicates_create_a_single_booking(): void
    {
        $class = $this->createClass('2026-11-02T22:00:00Z', '2026-11-02T23:00:00Z');

        $results = $this->race(array_fill(0, 6, [$class->uuid, 'ana']));

        $this->assertSame(1, BookingModel::query()->count());
        $this->assertEqualsCanonicalizing(['AlreadyBooked' => 5, 'confirmed' => 1], array_count_values($results));
    }

    private function createClass(string $start, string $end, int $capacity = 10): FitnessClassModel
    {
        return FitnessClassModel::factory()->between($start, $end)->capacity($capacity)->create();
    }

    /**
     * Forks one process per attempt; all of them start booking at the same instant.
     *
     * @param  list<array{string, string}>  $attempts  [classUuid, memberId]
     * @return list<string> booking status, or the short exception class name on failure
     */
    private function race(array $attempts): array
    {
        DB::disconnect();
        $startAt = microtime(true) + 0.5;
        $children = [];

        foreach ($attempts as $index => [$classUuid, $memberId]) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->runChild($index, $classUuid, $memberId, $startAt);
            }

            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $results = [];
        foreach (array_keys($attempts) as $index) {
            $file = "{$this->resultsDirectory}/{$index}";
            $results[] = is_file($file) ? file_get_contents($file) : 'crashed';
        }

        return $results;
    }

    private function runChild(int $index, string $classUuid, string $memberId, float $startAt): never
    {
        try {
            DB::purge();
            time_nanosleep(0, (int) max(0, ($startAt - microtime(true)) * 1e9));
            $result = app(BookSeat::class)->handle($classUuid, new MemberId($memberId))->status->value;
        } catch (Throwable $exception) {
            $result = class_basename($exception);
        }

        file_put_contents("{$this->resultsDirectory}/{$index}", $result);

        /** Skip PHPUnit's shutdown handlers in the child. */
        posix_kill(getmypid(), SIGKILL);
        exit(1);
    }
}
