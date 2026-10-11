<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\TimeOff;

use App\Enum\Type\HalfDay;
use App\Enum\Type\TimeOffType;
use App\Model\TimeOff\TimeOffDays;
use App\Model\TimeOff\TimeOffPeriod;
use PHPUnit\Framework\TestCase;

final class TimeOffDaysTest extends TestCase
{
    public function testAHolidayWithinAWeekCountsNothing(): void
    {
        $days = self::between('2026-11-09', HalfDay::Morning, '2026-11-13', HalfDay::Afternoon, ['2026-11-11' => 'Armistice 1918']);

        self::assertSame(8, $days->halfDayCount());
        self::assertSame([], $days->halvesOn(new \DateTimeImmutable('2026-11-11')));
    }

    public function testAnAfternoonStartAndAMorningEndCountHalfDays(): void
    {
        $days = self::between('2026-11-16', HalfDay::Afternoon, '2026-11-18', HalfDay::Morning);

        self::assertSame(4, $days->halfDayCount());
        self::assertSame([HalfDay::Afternoon], $days->halvesOn(new \DateTimeImmutable('2026-11-16')));
        self::assertSame([HalfDay::Morning, HalfDay::Afternoon], $days->halvesOn(new \DateTimeImmutable('2026-11-17')));
        self::assertSame([HalfDay::Morning], $days->halvesOn(new \DateTimeImmutable('2026-11-18')));
    }

    public function testASingleHalfDay(): void
    {
        $days = self::between('2026-11-18', HalfDay::Afternoon, '2026-11-18', HalfDay::Afternoon);

        self::assertSame(1, $days->halfDayCount());
        self::assertSame([HalfDay::Afternoon], $days->halvesOn(new \DateTimeImmutable('2026-11-18')));
    }

    public function testWeekendsCountNothing(): void
    {
        $days = self::between('2026-11-14', HalfDay::Morning, '2026-11-15', HalfDay::Afternoon);

        self::assertTrue($days->isEmpty());
    }

    public function testAnEndBeforeTheStartCoversNothing(): void
    {
        self::assertTrue(self::between('2026-11-18', HalfDay::Afternoon, '2026-11-18', HalfDay::Morning)->isEmpty());
    }

    public function testPaidLeaveAcrossMayIsSplitOverTwoPeriods(): void
    {
        $days = self::between('2027-04-29', HalfDay::Morning, '2027-05-04', HalfDay::Afternoon);

        self::assertSame(4, $days->halfDayCountIn(TimeOffPeriod::of(TimeOffType::PaidLeave, 2026)));
        self::assertSame(4, $days->halfDayCountIn(TimeOffPeriod::of(TimeOffType::PaidLeave, 2027)));
    }

    public function testRttAcrossNewYearIsSplitOverTwoPeriods(): void
    {
        $days = self::between('2026-12-31', HalfDay::Morning, '2027-01-04', HalfDay::Afternoon, ['2027-01-01' => 'Jour de l\'an']);

        self::assertSame(2, $days->halfDayCountIn(TimeOffPeriod::of(TimeOffType::Rtt, 2026)));
        self::assertSame(2, $days->halfDayCountIn(TimeOffPeriod::of(TimeOffType::Rtt, 2027)));
    }

    /**
     * @param array<string, string> $holidays
     */
    private static function between(string $start, HalfDay $startHalf, string $end, HalfDay $endHalf, array $holidays = []): TimeOffDays
    {
        return TimeOffDays::between(new \DateTimeImmutable($start), $startHalf, new \DateTimeImmutable($end), $endHalf, $holidays);
    }
}
