<?php

namespace Tests\Unit\Booking;

use App\Booking\Domain\Model\TimeRange;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimeRangeTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function ranges(): array
    {
        return [
            'identical' => ['10:00', '11:00', true],
            'starts inside' => ['10:30', '11:30', true],
            'ends inside' => ['09:30', '10:30', true],
            'contains' => ['09:00', '12:00', true],
            'contained' => ['10:15', '10:45', true],
            'back to back after' => ['11:00', '12:00', false],
            'back to back before' => ['09:00', '10:00', false],
            'disjoint' => ['13:00', '14:00', false],
        ];
    }

    #[DataProvider('ranges')]
    public function test_overlap_against_ten_to_eleven(string $start, string $end, bool $overlaps): void
    {
        $tenToEleven = $this->range('10:00', '11:00');
        $other = $this->range($start, $end);

        $this->assertSame($overlaps, $tenToEleven->overlaps($other));
        $this->assertSame($overlaps, $other->overlaps($tenToEleven));
    }

    public function test_rejects_end_not_after_start(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->range('10:00', '10:00');
    }

    private function range(string $start, string $end): TimeRange
    {
        return new TimeRange(new DateTimeImmutable("2026-11-02T{$start}:00Z"), new DateTimeImmutable("2026-11-02T{$end}:00Z"));
    }
}
