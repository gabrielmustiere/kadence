<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

/**
 * A run placed on the bar it belongs to, in percent of the width of that bar.
 */
final readonly class RoadmapSegment
{
    public function __construct(
        public RoadmapBar $bar,
        public RoadmapRun $run,
    ) {
    }
}
