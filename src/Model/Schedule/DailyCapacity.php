<?php

declare(strict_types=1);

namespace App\Model\Schedule;

use App\Enum\Type\HolidayCalendar;
use App\Service\WeeklyMaxManager;

/**
 * The capacity a person gives each working day: their weekly maximum, capped to the days that are not holidays, spread
 * evenly over those days. Counted in units of 1/6000 of a quarter, so that a share (%) of a maximum spread over one to
 * five days is always a whole number.
 */
final class DailyCapacity
{
    public const int UNITS_PER_QUARTER = 100 * self::DAY_DIVISOR;

    /** Divisible by every count of working days in a week (1 to 5). */
    private const int DAY_DIVISOR = 60;

    /** @var array<string, array{int, int}> working days and capped quarters, by user id and ISO week */
    private array $weeks = [];

    /**
     * @param array<int, array{HolidayCalendar, bool}>    $people      calendar and active flag, by user id
     * @param array<int, list<array{string, int<1, 20>}>> $weeklyMaxes Monday (Y-m-d) of effect and quarters, oldest first, by user id
     * @param array<string, array<string, true>>          $holidays    holidays (Y-m-d) by calendar value
     */
    public function __construct(
        private readonly array $people,
        private readonly array $weeklyMaxes,
        private readonly array $holidays,
    ) {
    }

    public function isActive(int $userId): bool
    {
        return $this->people[$userId][1] ?? false;
    }

    /**
     * A weekday that is not a holiday of the person's calendar.
     */
    public function isWorkingDay(int $userId, \DateTimeImmutable $day): bool
    {
        $calendar = $this->people[$userId][0] ?? null;

        return null !== $calendar && (int) $day->format('N') <= 5 && !isset($this->holidays[$calendar->value][$day->format('Y-m-d')]);
    }

    /**
     * @param int<25, 100> $share
     */
    public function units(int $userId, int $share, \DateTimeImmutable $day): int
    {
        if (!$this->isActive($userId) || !$this->isWorkingDay($userId, $day)) {
            return 0;
        }

        [$workingDays, $quarters] = $this->week($userId, $day);

        return 0 === $workingDays ? 0 : intdiv($share * $quarters * self::DAY_DIVISOR, $workingDays);
    }

    /**
     * @return array{int, int}
     */
    private function week(int $userId, \DateTimeImmutable $day): array
    {
        $key = $userId . '@' . $day->format('o-\WW');
        if (isset($this->weeks[$key])) {
            return $this->weeks[$key];
        }

        $monday = $day->modify('monday this week');
        $workingDays = 0;
        for ($offset = 0; $offset < 5; ++$offset) {
            if ($this->isWorkingDay($userId, $monday->modify(\sprintf('+%d days', $offset)))) {
                ++$workingDays;
            }
        }

        return $this->weeks[$key] = [$workingDays, WeeklyMaxManager::cap($this->quartersInEffect($userId, $monday->format('Y-m-d')), 5 - $workingDays)];
    }

    /**
     * @return int<1, 20>
     */
    private function quartersInEffect(int $userId, string $monday): int
    {
        $quarters = WeeklyMaxManager::DEFAULT_QUARTERS;
        foreach ($this->weeklyMaxes[$userId] ?? [] as [$from, $value]) {
            if ($from > $monday) {
                break;
            }
            $quarters = $value;
        }

        return $quarters;
    }
}
