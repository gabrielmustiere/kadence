<?php

declare(strict_types=1);

namespace App\Model\Schedule;

use App\Entity\Lot;
use App\Entity\User;

final readonly class ScheduleData
{
    /**
     * @param array<int, Lot>                $leaves      by lot id
     * @param array<int, LeafPlan>           $plans       by lot id
     * @param array<int, User>               $people      by user id
     * @param array<int, \DateTimeImmutable> $overrunDays day on which the time entered went beyond the estimate, by lot id
     */
    public function __construct(
        public array $leaves,
        public array $plans,
        public array $people,
        public DailyCapacity $capacity,
        public \DateTimeImmutable $today,
        public array $overrunDays = [],
    ) {
    }
}
