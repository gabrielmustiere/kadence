<?php

declare(strict_types=1);

namespace App\Model\TimeOff;

/**
 * The weekdays of a month, and who is away on each of them.
 */
final readonly class AbsenceMonth
{
    private const array MONTHS = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    /**
     * @param list<AbsenceRow> $rows
     */
    public function __construct(
        public \DateTimeImmutable $firstDay,
        public array $rows,
    ) {
    }

    public static function firstDayOf(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setTime(0, 0)->modify('first day of this month');
    }

    public function label(): string
    {
        return self::MONTHS[(int) $this->firstDay->format('n')] . ' ' . $this->firstDay->format('Y');
    }

    public function lastDay(): \DateTimeImmutable
    {
        return $this->firstDay->modify('last day of this month');
    }

    /**
     * @return list<\DateTimeImmutable> Monday to Friday
     */
    public function days(): array
    {
        $days = [];
        $last = $this->lastDay()->format('Y-m-d');
        for ($day = $this->firstDay; $day->format('Y-m-d') <= $last; $day = $day->modify('+1 day')) {
            if ((int) $day->format('N') <= 5) {
                $days[] = $day;
            }
        }

        return $days;
    }

    public function previous(): \DateTimeImmutable
    {
        return $this->firstDay->modify('-1 month');
    }

    public function next(): \DateTimeImmutable
    {
        return $this->firstDay->modify('+1 month');
    }
}
