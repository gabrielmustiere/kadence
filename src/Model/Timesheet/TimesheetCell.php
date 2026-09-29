<?php

declare(strict_types=1);

namespace App\Model\Timesheet;

final readonly class TimesheetCell
{
    /**
     * @param int<0, 4> $quarters
     * @param int<0, 4> $maxSelectable highest notch the day and week caps still allow, never below $quarters
     */
    public function __construct(
        public \DateTimeImmutable $day,
        public int $quarters,
        public int $maxSelectable,
        public bool $locked,
    ) {
    }

    public function isSelectable(int $quarters): bool
    {
        return !$this->locked && $quarters <= $this->maxSelectable;
    }
}
