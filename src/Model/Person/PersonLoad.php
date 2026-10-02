<?php

declare(strict_types=1);

namespace App\Model\Person;

use App\Model\Roadmap\RoadmapWindow;
use App\Model\Schedule\DailyCapacity;

/**
 * The load of a person from tomorrow on, the time entered being the past: the sum of their shares on the future parts
 * of the leaves of their teams.
 */
final readonly class PersonLoad
{
    private const int MAX_DAYS_OFF = 31;

    /**
     * @param list<LoadSpan>          $spans
     * @param \DateTimeImmutable|null $nextDay     the first working day of the person after today
     * @param \DateTimeImmutable|null $freeFrom    the first working day of the person after the last day they carry a
     *                                             load; null when unknown, or when they carry none
     * @param list<PersonLeafRow>     $unknownEnds the leaves of their teams not over yet without a calculated end, which
     *                                             leave $freeFrom unknown
     */
    public function __construct(
        public array $spans,
        public ?\DateTimeImmutable $nextDay,
        public int $nextDayPercent,
        public ?\DateTimeImmutable $freeFrom,
        public array $unknownEnds,
    ) {
    }

    /**
     * @param array<string, int>  $loads       summed shares by day (Y-m-d, in date order)
     * @param list<PersonLeafRow> $unknownEnds
     */
    public static function of(array $loads, DailyCapacity $capacity, int $userId, \DateTimeImmutable $today, RoadmapWindow $window, array $unknownEnds): self
    {
        $nextDay = self::workingDayAfter($capacity, $userId, $today);
        $lastDay = array_key_last($loads);

        return new self(
            self::spans($loads, $capacity, $userId, $window),
            $nextDay,
            null === $nextDay ? 0 : $loads[$nextDay->format('Y-m-d')] ?? 0,
            [] !== $unknownEnds || null === $lastDay ? null : self::workingDayAfter($capacity, $userId, new \DateTimeImmutable($lastDay)),
            $unknownEnds,
        );
    }

    /**
     * @param array<string, int> $loads summed shares by day (Y-m-d, in date order)
     *
     * @return list<LoadSpan>
     */
    private static function spans(array $loads, DailyCapacity $capacity, int $userId, RoadmapWindow $window): array
    {
        $placed = [];
        foreach (self::merge($loads, $capacity, $userId) as [$from, $to, $percent]) {
            $bar = $window->bar($from, $to);
            if (null !== $bar) {
                $placed[] = new LoadSpan($from, $to, $percent, $bar);
            }
        }

        return $placed;
    }

    /**
     * Days carrying the same load make one span until a working day of the person goes by with another load or none.
     *
     * @param array<string, int> $loads summed shares by day (Y-m-d, in date order)
     *
     * @return list<array{\DateTimeImmutable, \DateTimeImmutable, int}> first day, last day and load
     */
    private static function merge(array $loads, DailyCapacity $capacity, int $userId): array
    {
        $spans = [];
        foreach ($loads as $day => $percent) {
            $date = new \DateTimeImmutable($day);
            $last = array_key_last($spans);
            if (null !== $last && $spans[$last][2] === $percent && !$capacity->hasWorkingDayBetween([$userId], $spans[$last][1], $date)) {
                $spans[$last][1] = $date;
            } else {
                $spans[] = [$date, $date, $percent];
            }
        }

        return $spans;
    }

    private static function workingDayAfter(DailyCapacity $capacity, int $userId, \DateTimeImmutable $day): ?\DateTimeImmutable
    {
        for ($i = 1; $i <= self::MAX_DAYS_OFF; ++$i) {
            $next = $day->setTime(0, 0)->modify(\sprintf('+%d days', $i));
            if ($capacity->isWorkingDay($userId, $next)) {
                return $next;
            }
        }

        return null;
    }
}
