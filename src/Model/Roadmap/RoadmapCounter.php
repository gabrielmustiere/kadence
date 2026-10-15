<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Enum\Type\RoadmapSignal;

/**
 * A counter of the board of the roadmap: the leaves awaiting the action of a signal, and the projects they belong to.
 */
final readonly class RoadmapCounter
{
    /**
     * @param int<1, max> $leafCount
     * @param int<1, max> $projectCount
     */
    public function __construct(
        public RoadmapSignal $signal,
        public int $leafCount,
        public int $projectCount,
    ) {
    }
}
