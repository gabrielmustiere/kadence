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
     *
     * @param int<1, 20> $quarters
     *
     * @return int<0, 20>
     */
    public static function cap(int $quarters, int $holidayCount): int
    {
        return max(0, min($quarters, Quarters::PER_DAY * (5 - $holidayCount)));
    }
}
