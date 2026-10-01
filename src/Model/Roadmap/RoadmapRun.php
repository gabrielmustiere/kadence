<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

/**
 * Days entered on a leaf with no working day of its team left without entry between them.
 */
final readonly class RoadmapRun
{
    /**
     * @param list<RoadmapTeamLine> $team the members with their share and what they entered on the run, then the people
     *                                    outside the team who entered time on it
     */
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public int $quarters,
        public int $dayCount,
        public array $team = [],
    ) {
    }
}
