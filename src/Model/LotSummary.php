<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Model\Schedule\LeafProgress;

final readonly class LotSummary
{
    /**
     * @param list<LotSummary>  $children
     * @param int               $toEstimate        leaves without an estimate, the lot itself included when it is a leaf
     * @param int               $toAssign          leaves without an owner
     * @param int               $toReassign        leaves whose owner has been deactivated
     * @param bool              $hasTime           time is entered on the lot or one of its sub-lots
     * @param int               $remainingQuarters what is left to do on its leaves, from their progress when one is in
     *                                             force, never offset by an overrun
     * @param int               $overrunQuarters   what is entered beyond the estimate of its leaves, never offset by what is left
     * @param int               $progressPoints    the progress of its estimated leaves weighted by their estimate, in
     *                                             percent × quarters
     * @param LotProgress|null  $progress          the last progress declared on the leaf, none while « à estimer »
     * @param int|null          $projectedQuarters what the leaf will have cost once done, when a progress is in force
     * @param list<LotProgress> $declarations      every progress declared on the leaf, the latest first
     */
    public function __construct(
        public Lot $lot,
        public array $children,
        public int $estimateDays,
        public int $toEstimate,
        public int $toAssign,
        public int $toReassign,
        public bool $hasTime = false,
        public int $enteredQuarters = 0,
        public int $remainingQuarters = 0,
        public int $overrunQuarters = 0,
        public int $progressPoints = 0,
        public ?LotProgress $progress = null,
        public ?int $projectedQuarters = null,
        public array $declarations = [],
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
     * How far the projected cost of the leaf goes beyond its estimate, below 0 when it stays under; null without progress.
     */
    public function projectedGapQuarters(): ?int
    {
        return null === $this->projectedQuarters ? null : $this->projectedQuarters - $this->estimateDays * Quarters::PER_DAY;
    }

    public function isPartial(): bool
    {
        return $this->toEstimate > 0;
    }
}
