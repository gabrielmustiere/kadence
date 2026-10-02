<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\WeeklyMaximum;
use PHPUnit\Framework\TestCase;

final class WeeklyMaximumTest extends TestCase
{
    public function testAWeekWithoutHolidayKeepsItsMaximum(): void
    {
        self::assertSame(18, WeeklyMaximum::cap(18, 0));
    }

    public function testEachHolidayTakesAFullDayOffTheWeek(): void
    {
        self::assertSame(16, WeeklyMaximum::cap(20, 1));
        self::assertSame(12, WeeklyMaximum::cap(14, 2));
    }

    public function testAPartTimeMaximumBelowTheDaysLeftIsKept(): void
    {
        self::assertSame(10, WeeklyMaximum::cap(10, 2));
    }

    public function testAWeekOfHolidaysLeavesNothingToEnter(): void
    {
        self::assertSame(0, WeeklyMaximum::cap(20, 5));
    }
}
