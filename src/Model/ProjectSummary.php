<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Project;

final readonly class ProjectSummary
{
    /**
     * @param list<LotSummary> $lots top-level lots, each carrying its sub-lots
     */
    public function __construct(
        public Project $project,
        public array $lots,
        public int $estimateDays,
        public int $subLotCount,
        public int $toEstimate,
        public int $toAssign,
        public int $toReassign,
    ) {
    }

    public function isPartial(): bool
    {
        return $this->toEstimate > 0;
    }

    public function isUnsplit(): bool
    {
        return [] === $this->lots;
    }
}
