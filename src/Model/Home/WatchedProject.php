<?php

declare(strict_types=1);

namespace App\Model\Home;

use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\RoadmapRow;

final readonly class WatchedProject
{
    /**
     * @param list<RoadmapSignal> $signals what its leaves raise that calls for a look, or that withholds its end
     */
    public function __construct(
        public RoadmapRow $row,
        public array $signals,
    ) {
    }
}
