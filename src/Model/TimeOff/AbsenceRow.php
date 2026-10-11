<?php

declare(strict_types=1);

namespace App\Model\TimeOff;

use App\Entity\User;

/**
 * A person on the absence page: their holidays and their time off, pending requests and types only when the viewer may
 * see them.
 */
final readonly class AbsenceRow
{
    /**
     * @param array<string, string>     $holidays labels by day (Y-m-d)
     * @param array<string, TimeOffDay> $days     time off by day (Y-m-d)
     */
    public function __construct(
        public User $person,
        public bool $detail,
        public array $holidays,
        public array $days,
    ) {
    }
}
