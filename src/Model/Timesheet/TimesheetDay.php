<?php

declare(strict_types=1);

namespace App\Model\Timesheet;

use App\Model\Quarters;

final readonly class TimesheetDay
{
    /**
     * @param non-empty-string|null $holiday the label of the holiday falling on this day
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public int $quarters,
        public bool $today,
        public bool $forgotten,
        public ?string $holiday,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->quarters >= Quarters::PER_DAY;
    }
}
