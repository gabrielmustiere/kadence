<?php

declare(strict_types=1);

namespace App\Model\Schedule;

use App\Enum\Type\HolidayCalendar;
use App\Model\WeeklyMaximum;

/**
 * The capacity a person gives each working day: their weekly maximum, capped to the days that are not holidays, spread
 * evenly over those days. A day of approved time off gives nothing and a half-day off half of it, without spreading the
 * week over fewer days. Counted in units of 1/12000 of a quarter, so that a share (%) of a maximum spread over one to
 * five days, and its half, is always a whole number.
 */
final class DailyCapacity
{
    public const int UNITS_PER_QUARTER = 100 * self::DAY_DIVISOR;

    /** Divisible by every count of working days in a week (1 to 5), and still once halved. */
    private const int DAY_DIVISOR = 120;

    /** @var array<string, array{int, int}> working days and capped quarters, by user id and ISO week */
    private array $weeks = [];

    /**
     * @param array<int, array{HolidayCalendar, bool}>    $people      calendar and active flag, by user id
     * @param array<int, list<array{string, int<1, 20>}>> $weeklyMaxes Monday (Y-m-d) of effect and quarters, oldest first, by user id
     * @param array<string, array<string, true>>          $holidays    holidays (Y-m-d) by calendar value
     * @param array<int, array<string, int<1, 2>>>        $timeOff     half-days of approved time off by user id and day (Y-m-d)
     */
    public function __construct(
        private readonly array $people,
        private readonly array $weeklyMaxes,
        private readonly array $holidays,
        private readonly array $timeOff = [],
    ) {
    }

    public function isActive(int $userId): bool
    {
        return $this->people[$userId][1] ?? false;
    }

    /**
     * A weekday that is neither a holiday of the person's calendar nor a whole day of approved time off.
     */
    public function isWorkingDay(int $userId, \DateTimeImmutable $day): bool
    {
        return $this->isScheduledDay($userId, $day) && 2 > $this->timeOffHalves($userId, $day);
    }

    /**
     * @return int<0, 2> the half-days of approved time off of the person on that day, none on a weekend or a holiday
     */
    public function timeOffHalves(int $userId, \DateTimeImmutable $day): int
    {
        return $this->isScheduledDay($userId, $day) ? $this->timeOff[$userId][$day->format('Y-m-d')] ?? 0 : 0;
    }

    /**
     * The approved time off of the person between two days included: whole days off in a row make one span, across
     * weekends and holidays; a half-day off is a span of its own.
     *
     * @return list<array{\DateTimeImmutable, \DateTimeImmutable, bool}> first day, last day, and whether it is a half-day
     */
    public function timeOffSpans(int $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $spans = [];
        $open = null;
        foreach ($this->scheduledDays($userId, $from, $to) as [$day, $halves]) {
            if (null !== $open && 2 === $halves && !$open[2]) {
                $open[1] = $day;
                continue;
            }
            if (null !== $open) {
                $spans[] = $open;
                $open = null;
            }
            if (0 < $halves) {
                $open = [$day, $day, 1 === $halves];
            }
        }

        return null === $open ? $spans : [...$spans, $open];
    }

    /**
     * @return list<array{\DateTimeImmutable, int<0, 2>}> each weekday that is not a holiday of the person, with their half-days off
     */
    private function scheduledDays(int $userId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $days = [];
        $last = $to->format('Y-m-d');
        for ($day = $from->setTime(0, 0); $day->format('Y-m-d') <= $last; $day = $day->modify('+1 day')) {
            if ($this->isScheduledDay($userId, $day)) {
                $days[] = [$day, $this->timeOff[$userId][$day->format('Y-m-d')] ?? 0];
            }
        }

        return $days;
    }

    /**
     * Whether a day strictly between $after and $before is a working day for at least one of these people.
     *
     * @param list<int> $userIds
     */
    public function hasWorkingDayBetween(array $userIds, \DateTimeImmutable $after, \DateTimeImmutable $before): bool
    {
        $last = $before->format('Y-m-d');
        for ($day = $after->setTime(0, 0)->modify('+1 day'); $day->format('Y-m-d') < $last; $day = $day->modify('+1 day')) {
            foreach ($userIds as $userId) {
                if ($this->isWorkingDay($userId, $day)) {
                    return true;
                }
            }
        }

        return false;
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
        if (0 === $workingDays) {
            return 0;
        }

        $units = intdiv($share * $quarters * self::DAY_DIVISOR, $workingDays);

        return 1 === $this->timeOffHalves($userId, $day) ? intdiv($units, 2) : $units;
    }

    /**
     * A weekday that is not a holiday of the person's calendar: the days their weekly maximum is spread over.
     */
    private function isScheduledDay(int $userId, \DateTimeImmutable $day): bool
    {
        $calendar = $this->people[$userId][0] ?? null;

        return null !== $calendar && (int) $day->format('N') <= 5 && !isset($this->holidays[$calendar->value][$day->format('Y-m-d')]);
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
            if ($this->isScheduledDay($userId, $monday->modify(\sprintf('+%d days', $offset)))) {
                ++$workingDays;
            }
        }

        return $this->weeks[$key] = [$workingDays, WeeklyMaximum::cap($this->quartersInEffect($userId, $monday->format('Y-m-d')), 5 - $workingDays)];
    }

    /**
     * @return int<1, 20>
     */
    private function quartersInEffect(int $userId, string $monday): int
    {
        $quarters = WeeklyMaximum::DEFAULT_QUARTERS;
        foreach ($this->weeklyMaxes[$userId] ?? [] as [$from, $value]) {
            if ($from > $monday) {
                break;
            }
            $quarters = $value;
        }

        return $quarters;
    }
}
