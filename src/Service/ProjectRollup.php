<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\Project;
use App\Model\LotSummary;
use App\Model\ProjectSummary;
use App\Model\Quarters;

final readonly class ProjectRollup
{
    /**
     * @param array<int, int> $quartersByLot time entered on each lot, by lot id
     */
    public function summarize(Project $project, array $quartersByLot = []): ProjectSummary
    {
        $lots = [];
        $subLotCount = 0;
        foreach ($project->getLots() as $lot) {
            if ($lot->isSubLot()) {
                ++$subLotCount;
                continue;
            }
            $lots[] = $this->summarizeLot($lot, $quartersByLot);
        }

        [$estimateDays, $toEstimate, $toAssign, $toReassign, $entered, $remaining, $overrun] = self::sum($lots);

        return new ProjectSummary($project, $lots, $estimateDays, $subLotCount, $toEstimate, $toAssign, $toReassign, self::anyHasTime($lots), $entered, $remaining, $overrun);
    }

    /**
     * @param array<int, int> $quartersByLot
     */
    private function summarizeLot(Lot $lot, array $quartersByLot): LotSummary
    {
        if ($lot->isLeaf()) {
            $estimateDays = $lot->getEstimateDays();
            $owner = $lot->getOwner();
            $entered = $quartersByLot[$lot->getId() ?? 0] ?? 0;
            $left = null === $estimateDays ? 0 : $estimateDays * Quarters::PER_DAY - $entered;

            return new LotSummary(
                $lot,
                [],
                $estimateDays ?? 0,
                null === $estimateDays ? 1 : 0,
                null === $owner ? 1 : 0,
                null !== $owner && !$owner->isActive() ? 1 : 0,
                $entered > 0,
                $entered,
                max(0, $left),
                max(0, -$left),
            );
        }

        $children = array_map(fn (Lot $child): LotSummary => $this->summarizeLot($child, $quartersByLot), $lot->getChildren()->getValues());
        [$estimateDays, $toEstimate, $toAssign, $toReassign, $entered, $remaining, $overrun] = self::sum($children);

        return new LotSummary($lot, $children, $estimateDays, $toEstimate, $toAssign, $toReassign, self::anyHasTime($children), $entered, $remaining, $overrun);
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
     * @return array{int, int, int, int, int, int, int} estimate days, then leaves to estimate, to assign and to reassign,
     *                                                  then quarters entered, remaining and overrun
     */
    private static function sum(array $summaries): array
    {
        $totals = [0, 0, 0, 0, 0, 0, 0];
        foreach ($summaries as $summary) {
            $totals[0] += $summary->estimateDays;
            $totals[1] += $summary->toEstimate;
            $totals[2] += $summary->toAssign;
            $totals[3] += $summary->toReassign;
            $totals[4] += $summary->enteredQuarters;
            $totals[5] += $summary->remainingQuarters;
            $totals[6] += $summary->overrunQuarters;
        }

        return $totals;
    }
}
