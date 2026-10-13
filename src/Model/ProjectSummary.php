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

    /**
     * What the project will have cost once done: the overrun is already in the time entered, and what is left on a leaf
     * never offsets the overrun of another.
     */
    public function projectedQuarters(): int
    {
        return $this->enteredQuarters + $this->remainingQuarters;
    }

    /**
     * How far the projected cost goes beyond the estimate, below 0 when it stays under.
     */
    public function projectedGapQuarters(): int
    {
        return $this->projectedQuarters() - $this->estimateDays * Quarters::PER_DAY;
    }

    /**
     * The projected cost is only a minimum while a leaf goes on with no remaining known.
     */
    public function isProjectedAtLeast(): bool
    {
        return array_any(
            $this->lots,
            static fn (LotSummary $lot): bool => $lot->isRemainingUnknown() || array_any($lot->children, static fn (LotSummary $subLot): bool => $subLot->isRemainingUnknown()),
        );
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
