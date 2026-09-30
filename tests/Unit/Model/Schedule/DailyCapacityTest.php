<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Schedule;

use App\Enum\Type\HolidayCalendar;
use App\Model\Schedule\DailyCapacity;
use PHPUnit\Framework\TestCase;

final class DailyCapacityTest extends TestCase
{
    private const int ALICE = 1;
    private const int BRUNO = 2;
    private const int CLARA = 3;

    public function testFullTimePersonGivesAFullDayEachWorkingDay(): void
    {
        $capacity = $this->capacity();

        self::assertSame(4 * DailyCapacity::UNITS_PER_QUARTER, $capacity->units(self::ALICE, 100, $this->day('2026-10-05')));
        self::assertSame(2 * DailyCapacity::UNITS_PER_QUARTER, $capacity->units(self::ALICE, 50, $this->day('2026-10-05')));
    }

    public function testWeekendGivesNothing(): void
    {
        self::assertSame(0, $this->capacity()->units(self::ALICE, 100, $this->day('2026-10-10')));
        self::assertFalse($this->capacity()->isWorkingDay(self::ALICE, $this->day('2026-10-11')));
    }

    public function testPartTimeIsSpreadEvenlyOverTheWeek(): void
    {
        $capacity = $this->capacity(weeklyMaxes: [self::ALICE => [['2026-01-05', 16]]]);

        $week = 0;
        foreach (['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09'] as $day) {
            $week += $capacity->units(self::ALICE, 100, $this->day($day));
        }

        self::assertSame(16 * DailyCapacity::UNITS_PER_QUARTER, $week);
        self::assertSame(intdiv(16 * DailyCapacity::UNITS_PER_QUARTER, 5), $capacity->units(self::ALICE, 100, $this->day('2026-10-05')));
    }

    public function testHolidayGivesNothingAndTheWeekIsCappedToTheOtherDays(): void
    {
        $capacity = $this->capacity(holidays: ['fr' => ['2026-10-07' => true]]);

        self::assertSame(0, $capacity->units(self::ALICE, 100, $this->day('2026-10-07')));
        self::assertSame(4 * DailyCapacity::UNITS_PER_QUARTER, $capacity->units(self::ALICE, 100, $this->day('2026-10-06')));
        self::assertTrue($capacity->isWorkingDay(self::BRUNO, $this->day('2026-10-07')), 'A French holiday is a working day on the Belgian calendar.');
    }

    public function testPartTimeWeekWithAHolidayIsSpreadOverTheRemainingDays(): void
    {
        $capacity = $this->capacity(weeklyMaxes: [self::ALICE => [['2026-01-05', 12]]], holidays: ['fr' => ['2026-10-07' => true]]);

        self::assertSame(3 * DailyCapacity::UNITS_PER_QUARTER, $capacity->units(self::ALICE, 100, $this->day('2026-10-05')));
    }

    public function testWeeklyMaximumInEffectFollowsItsHistory(): void
    {
        $capacity = $this->capacity(weeklyMaxes: [self::ALICE => [['2026-01-05', 10], ['2026-10-12', 20]]]);

        self::assertSame(2 * DailyCapacity::UNITS_PER_QUARTER, $capacity->units(self::ALICE, 100, $this->day('2026-10-09')));
        self::assertSame(4 * DailyCapacity::UNITS_PER_QUARTER, $capacity->units(self::ALICE, 100, $this->day('2026-10-12')));
    }

    public function testInactiveOrUnknownPersonGivesNothing(): void
    {
        $capacity = $this->capacity();

        self::assertFalse($capacity->isActive(self::CLARA));
        self::assertSame(0, $capacity->units(self::CLARA, 100, $this->day('2026-10-05')));
        self::assertSame(0, $capacity->units(99, 100, $this->day('2026-10-05')));
    }

    /**
     * @param array<int, list<array{string, int<1, 20>}>> $weeklyMaxes
     * @param array<string, array<string, true>>          $holidays
     */
    private function capacity(array $weeklyMaxes = [], array $holidays = []): DailyCapacity
    {
        return new DailyCapacity(
            [self::ALICE => [HolidayCalendar::France, true], self::BRUNO => [HolidayCalendar::Belgium, true], self::CLARA => [HolidayCalendar::France, false]],
            $weeklyMaxes,
            $holidays,
        );
    }

    private function day(string $day): \DateTimeImmutable
    {
        return new \DateTimeImmutable($day);
    }
}
