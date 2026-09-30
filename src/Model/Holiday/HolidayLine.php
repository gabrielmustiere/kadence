<?php

declare(strict_types=1);

namespace App\Model\Holiday;

use App\Entity\HolidayAdjustment;

/**
 * A line of the yearly view of a calendar: a legal holiday, possibly removed, or an added one.
 */
final readonly class HolidayLine
{
    public function __construct(
        public \DateTimeImmutable $day,
        public string $label,
        public ?HolidayAdjustment $adjustment,
    ) {
    }

    public function isAdded(): bool
    {
        return true === $this->adjustment?->isAdded();
    }

    public function isRemoved(): bool
    {
        return false === $this->adjustment?->isAdded();
    }

    public function isWeekend(): bool
    {
        return (int) $this->day->format('N') > 5;
    }
}
