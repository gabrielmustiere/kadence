<?php

declare(strict_types=1);

namespace App\Model;

/**
 * The quarters a person may enter in a week.
 */
final class WeeklyMaximum
{
    public const int DEFAULT_QUARTERS = 20;

    /**
     * The maximum of a week once its holidays are left out: never more than a full day per day that is not a holiday.
     * Time off then takes its share of what is left, prorated over the half-days of the days that are not holidays and
     * rounded down to the quarter, so that a part-time person is not asked to enter more than they work.
     *
     * @param int<1, 20> $quarters
     *
     * @return int<0, 20>
     */
    public static function cap(int $quarters, int $holidayCount, int $timeOffHalfDays = 0): int
    {
        $capped = max(0, min($quarters, Quarters::PER_DAY * (5 - $holidayCount)));
        $halfDays = 2 * (5 - $holidayCount);
        if (0 === $timeOffHalfDays || $halfDays <= 0) {
            return $capped;
        }

        return max(0, min($capped, intdiv($capped * max(0, $halfDays - $timeOffHalfDays), $halfDays)));
    }
}
