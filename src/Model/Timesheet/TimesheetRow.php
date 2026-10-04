<?php

declare(strict_types=1);

namespace App\Model\Timesheet;

use App\Entity\Lot;

final readonly class TimesheetRow
{
    /**
     * @param list<TimesheetCell> $cells Monday to Friday
     */
    public function __construct(
        public Lot $lot,
        public array $cells,
        public bool $favorite,
    ) {
    }
}
