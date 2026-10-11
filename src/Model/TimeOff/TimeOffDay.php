<?php

declare(strict_types=1);

namespace App\Model\TimeOff;

use App\Entity\TimeOffRequest;
use App\Enum\Type\HalfDay;
use App\Enum\Type\TimeOffStatus;
use App\Enum\Type\TimeOffType;
use App\Model\Quarters;

/**
 * The pending or approved time off of a person on one day of their timesheet.
 */
final readonly class TimeOffDay
{
    /**
     * @param non-empty-list<array{HalfDay, TimeOffType, TimeOffStatus}> $halves in morning then afternoon order
     */
    public function __construct(
        public array $halves,
    ) {
    }

    /**
     * The pending and approved time off of these requests on each working day between two days included.
     *
     * @param list<TimeOffRequest>  $requests
     * @param array<string, string> $holidays by day (Y-m-d)
     *
     * @return array<string, self> by day (Y-m-d, in date order)
     */
    public static function collect(array $requests, array $holidays, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $days = [];
        foreach (self::halvesByDay($requests, $holidays, $from->format('Y-m-d'), $to->format('Y-m-d')) as $day => $halves) {
            $ordered = array_values(array_filter(array_map(static fn (HalfDay $half): ?array => $halves[$half->value] ?? null, HalfDay::cases())));
            if ([] !== $ordered) {
                $days[$day] = new self($ordered);
            }
        }

        return $days;
    }

    /**
     * @param list<TimeOffRequest>  $requests
     * @param array<string, string> $holidays by day (Y-m-d)
     *
     * @return array<string, array<string, array{HalfDay, TimeOffType, TimeOffStatus}>> by day (Y-m-d, in date order), then by half-day
     */
    private static function halvesByDay(array $requests, array $holidays, string $first, string $last): array
    {
        $byDay = [];
        foreach ($requests as $request) {
            $within = array_filter(TimeOffDays::of($request, $holidays)->halves, static fn (string $day): bool => $day >= $first && $day <= $last, \ARRAY_FILTER_USE_KEY);
            foreach ($within as $day => $halves) {
                foreach ($halves as $half) {
                    $byDay[$day][$half->value] = [$half, $request->getType(), $request->getStatus()];
                }
            }
        }
        ksort($byDay);

        return $byDay;
    }

    public function halfDayCount(): int
    {
        return \count($this->halves);
    }

    /**
     * @param array<string, self> $days
     */
    public static function halfDayCountOf(array $days): int
    {
        return array_sum(array_map(static fn (self $day): int => $day->halfDayCount(), $days));
    }

    public function isWhole(): bool
    {
        return 2 <= \count($this->halves);
    }

    /**
     * The quarters left to enter on the day: none on a whole day off, half a day on a half-day off.
     *
     * @return int<0, 4>
     */
    public function capQuarters(): int
    {
        return $this->isWhole() ? 0 : Quarters::PER_DAY / 2;
    }

    public function label(): string
    {
        [, $type, $status] = $this->halves[0];
        $alike = array_all($this->halves, static fn (array $half): bool => $half[1] === $type && $half[2] === $status);
        if ($this->isWhole() && $alike) {
            return \sprintf('Congé · %s%s', $type->label(), TimeOffStatus::Pending === $status ? ' · en attente' : '');
        }

        return 'Congé · ' . implode(' · ', array_map(
            static fn (array $half): string => \sprintf('%s (%s%s)', $half[1]->label(), $half[0]->label(), TimeOffStatus::Pending === $half[2] ? ', en attente' : ''),
            $this->halves,
        ));
    }
}
