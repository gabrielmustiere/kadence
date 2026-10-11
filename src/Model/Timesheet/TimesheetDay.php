<?php

declare(strict_types=1);

namespace App\Model\Timesheet;

use App\Model\TimeOff\TimeOffDay;

final readonly class TimesheetDay
{
    /**
     * @param non-empty-string|null $holiday     the label of the holiday falling on this day
     * @param int<0, 4>             $capQuarters the quarters the day takes: none on a holiday or a whole day off
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public int $quarters,
        public bool $today,
        public bool $forgotten,
        public ?string $holiday,
        public int $capQuarters,
        public ?TimeOffDay $timeOff = null,
    ) {
    }

    /**
     * A day that takes nothing is never complete: it is not worked.
     */
    public function isComplete(): bool
    {
        return 0 < $this->capQuarters && $this->quarters >= $this->capQuarters;
    }
}
