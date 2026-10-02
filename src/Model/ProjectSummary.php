<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Project;
use App\Model\Schedule\LeafProgress;

final readonly class ProjectSummary
{
    /**
     * @param list<LotSummary> $lots              top-level lots, each carrying its sub-lots
     * @param int              $remainingQuarters what is left to do on its leaves, never offset by an overrun
     * @param int              $overrunQuarters   what is entered beyond the estimate of its leaves, never offset by what is left
     * @param int              $progressPoints    the progress of its estimated leaves weighted by their estimate
     */
    public function __construct(
        public Project $project,
        public array $lots,
        public int $estimateDays,
        public int $subLotCount,
        public int $toEstimate,
        public int $toAssign,
        public int $toReassign,
        public bool $hasTime = false,
        public int $enteredQuarters = 0,
        public int $remainingQuarters = 0,
        public int $overrunQuarters = 0,
        public int $progressPoints = 0,
    ) {
    }

    /**
     * The progress of its estimated leaves, weighted by their estimate; null while none is estimated.
     */
    public function progressPercent(): ?int
    {
        return LeafProgress::weightedPercent($this->progressPoints, $this->estimateDays);
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
