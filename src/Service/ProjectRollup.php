<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Entity\Project;
use App\Model\LotSummary;
use App\Model\ProjectSummary;
use App\Model\Quarters;
use App\Model\Schedule\LeafProgress;
use App\Repository\LotProgressRepository;
use App\Repository\TimeEntryRepository;

final readonly class ProjectRollup
{
    public function __construct(
        private TimeEntryRepository $timeEntryRepository,
        private LotProgressRepository $lotProgressRepository,
    ) {
    }

    /**
     * The project with the time entered and the progress declared on its leaves.
     */
    public function summarize(Project $project): ProjectSummary
    {
        return $this->rollUp($project, $this->timeEntryRepository->sumQuartersByLot($project), $this->lotProgressRepository->findForProjectByLot($project));
    }

    /**
     * The estimates and the leaves left to settle alone, at no query: neither the time entered nor the progress declared
     * is read, and what derives from them stays at zero.
     */
    public function outline(Project $project): ProjectSummary
    {
        return $this->rollUp($project, [], []);
    }

    /**
     * @param array<int, int>                         $quartersByLot     time entered on each lot, by lot id
     * @param array<int, non-empty-list<LotProgress>> $declarationsByLot progress declared on each lot, the latest first
     */
    private function rollUp(Project $project, array $quartersByLot, array $declarationsByLot): ProjectSummary
    {
        $lots = [];
        $subLotCount = 0;
        foreach ($project->getLots() as $lot) {
            if ($lot->isSubLot()) {
                ++$subLotCount;
                continue;
            }
            $lots[] = $this->summarizeLot($lot, $quartersByLot, $declarationsByLot);
        }

        [$estimateDays, $toEstimate, $toAssign, $toReassign, $entered, $remaining, $overrun, $progressPoints] = self::sum($lots);

        return new ProjectSummary($project, $lots, $estimateDays, $subLotCount, $toEstimate, $toAssign, $toReassign, self::anyHasTime($lots), $entered, $remaining, $overrun, $progressPoints);
    }

    /**
     * @param array<int, int>                         $quartersByLot
     * @param array<int, non-empty-list<LotProgress>> $declarationsByLot
     */
    private function summarizeLot(Lot $lot, array $quartersByLot, array $declarationsByLot): LotSummary
    {
        if ($lot->isLeaf()) {
            return self::summarizeLeaf($lot, $quartersByLot[$lot->getId() ?? 0] ?? 0, $declarationsByLot[$lot->getId() ?? 0] ?? []);
        }

        $children = array_map(fn (Lot $child): LotSummary => $this->summarizeLot($child, $quartersByLot, $declarationsByLot), $lot->getChildren()->getValues());
        [$estimateDays, $toEstimate, $toAssign, $toReassign, $entered, $remaining, $overrun, $progressPoints] = self::sum($children);

        return new LotSummary($lot, $children, $estimateDays, $toEstimate, $toAssign, $toReassign, self::anyHasTime($children), $entered, $remaining, $overrun, $progressPoints);
    }

    /**
     * What is left to do follows the progress in force, while the overrun is always measured against the estimate. A
     * leaf back to « à estimer » keeps its declarations but has no progress.
     *
     * @param list<LotProgress> $declarations the latest first
     */
    private static function summarizeLeaf(Lot $leaf, int $entered, array $declarations): LotSummary
    {
        $estimateDays = $leaf->getEstimateDays();
        if (null === $estimateDays) {
            return self::leafSummary($leaf, $entered, $declarations);
        }

        $estimate = $estimateDays * Quarters::PER_DAY;
        $declaration = $declarations[0] ?? null;
        $progress = null === $declaration ? null : LeafProgress::fromDeclaration($declaration);
        $remaining = max(0, $progress?->remainingAfter($entered) ?? $estimate - $entered);

        return self::leafSummary($leaf, $entered, $declarations, $remaining, max(0, $entered - $estimate), LeafProgress::pointsOf($progress, $entered, $estimate), null === $progress ? null : $entered + $remaining);
    }

    /**
     * @param list<LotProgress> $declarations the latest first
     */
    private static function leafSummary(Lot $leaf, int $entered, array $declarations, int $remaining = 0, int $overrun = 0, int $progressPoints = 0, ?int $projected = null): LotSummary
    {
        $estimateDays = $leaf->getEstimateDays();
        $owner = $leaf->getOwner();

        return new LotSummary(
            $leaf,
            [],
            $estimateDays ?? 0,
            null === $estimateDays ? 1 : 0,
            null === $owner ? 1 : 0,
            null !== $owner && !$owner->isActive() ? 1 : 0,
            $entered > 0,
            $entered,
            $remaining,
            $overrun,
            $progressPoints,
            null === $estimateDays ? null : $declarations[0] ?? null,
            $projected,
            $declarations,
        );
    }

    /**
     * @param list<LotSummary> $summaries
     */
    private static function anyHasTime(array $summaries): bool
    {
        return array_any($summaries, static fn (LotSummary $summary): bool => $summary->hasTime);
    }

    /**
     * @param list<LotSummary> $summaries
     *
     * @return array{int, int, int, int, int, int, int, int} estimate days, then leaves to estimate, to assign and to
     *                                                       reassign, then quarters entered, remaining and overrun,
     *                                                       then progress points
     */
    private static function sum(array $summaries): array
    {
        $totals = [0, 0, 0, 0, 0, 0, 0, 0];
        foreach ($summaries as $summary) {
            $totals[0] += $summary->estimateDays;
            $totals[1] += $summary->toEstimate;
            $totals[2] += $summary->toAssign;
            $totals[3] += $summary->toReassign;
            $totals[4] += $summary->enteredQuarters;
            $totals[5] += $summary->remainingQuarters;
            $totals[6] += $summary->overrunQuarters;
            $totals[7] += $summary->progressPoints;
        }

        return $totals;
    }
}
