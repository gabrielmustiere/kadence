<?php

declare(strict_types=1);

namespace App\Model\TimeOff;

use App\Entity\TimeOffRequest;
use App\Enum\Type\HalfDay;

/**
 * The half-days a request covers, on weekdays that are not holidays: a weekend or a holiday within it counts nothing.
 */
final readonly class TimeOffDays
{
    /**
     * @param array<string, non-empty-list<HalfDay>> $halves half-days by day (Y-m-d, in date order)
     */
    private function __construct(
        public array $halves,
    ) {
    }

    /**
     * @param array<string, mixed> $holidays keyed by day (Y-m-d)
     */
    public static function between(\DateTimeImmutable $startDay, HalfDay $startHalf, \DateTimeImmutable $endDay, HalfDay $endHalf, array $holidays = []): self
    {
        $first = $startDay->format('Y-m-d');
        $last = $endDay->format('Y-m-d');

        $halves = [];
        for ($day = $startDay->setTime(0, 0); $day->format('Y-m-d') <= $last; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            if ((int) $day->format('N') > 5 || isset($holidays[$date])) {
                continue;
            }

            $covered = [];
            if ($date !== $first || HalfDay::Morning === $startHalf) {
                $covered[] = HalfDay::Morning;
            }
            if ($date !== $last || HalfDay::Afternoon === $endHalf) {
                $covered[] = HalfDay::Afternoon;
            }
            if ([] !== $covered) {
                $halves[$date] = $covered;
            }
        }

        return new self($halves);
    }

    /**
     * @param array<string, mixed> $holidays keyed by day (Y-m-d)
     */
    public static function of(TimeOffRequest $request, array $holidays = []): self
    {
        return self::between($request->getStartDay(), $request->getStartHalf(), $request->getEndDay(), $request->getEndHalf(), $holidays);
    }

    public function isEmpty(): bool
    {
        return [] === $this->halves;
    }

    public function halfDayCount(): int
    {
        return array_sum(array_map(\count(...), $this->halves));
    }

    public function halfDayCountIn(TimeOffPeriod $period): int
    {
        $count = 0;
        foreach ($this->halves as $day => $halves) {
            if ($period->contains(new \DateTimeImmutable($day))) {
                $count += \count($halves);
            }
        }

        return $count;
    }

    /**
     * @return list<HalfDay>
     */
    public function halvesOn(\DateTimeImmutable $day): array
    {
        return $this->halves[$day->format('Y-m-d')] ?? [];
    }
}
