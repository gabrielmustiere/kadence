<?php

declare(strict_types=1);

namespace App\Model\Person;

use App\Model\Roadmap\RoadmapBar;
use App\Model\Schedule\ScheduleResult;

/**
 * Working days in a row on which the person carries the same load.
 */
final readonly class LoadSpan
{
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public int $percent,
        public RoadmapBar $bar,
    ) {
    }

    public function isOverload(): bool
    {
        return $this->percent > ScheduleResult::FULL_LOAD;
    }
}
