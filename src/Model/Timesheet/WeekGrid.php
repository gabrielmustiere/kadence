<?php

declare(strict_types=1);

namespace App\Model\Timesheet;

use App\Model\Week;

final readonly class WeekGrid
{
    /**
     * @param list<TimesheetDay> $days Monday to Friday
     * @param list<TimesheetRow> $rows
     */
    public function __construct(
        public Week $week,
        public array $days,
        public array $rows,
        public int $quarters,
        public int $maxQuarters,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->quarters >= $this->maxQuarters;
    }
}
