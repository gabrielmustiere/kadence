<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\Project;
use App\Model\LotSummary;
use App\Model\ProjectSummary;

final readonly class ProjectRollup
{
    public function summarize(Project $project): ProjectSummary
    {
        $lots = [];
        $subLotCount = 0;
        foreach ($project->getLots() as $lot) {
            if ($lot->isSubLot()) {
                ++$subLotCount;
                continue;
            }
            $lots[] = $this->summarizeLot($lot);
        }

        [$estimateDays, $toEstimate, $toAssign, $toReassign] = self::sum($lots);

        return new ProjectSummary($project, $lots, $estimateDays, $subLotCount, $toEstimate, $toAssign, $toReassign);
    }

    private function summarizeLot(Lot $lot): LotSummary
    {
        if ($lot->isLeaf()) {
            $estimateDays = $lot->getEstimateDays();
            $owner = $lot->getOwner();

            return new LotSummary(
                $lot,
                [],
                $estimateDays ?? 0,
                null === $estimateDays ? 1 : 0,
                null === $owner ? 1 : 0,
                null !== $owner && !$owner->isActive() ? 1 : 0,
            );
        }

        $children = array_map($this->summarizeLot(...), $lot->getChildren()->getValues());

        return new LotSummary($lot, $children, ...self::sum($children));
    }

    /**
     * @param list<LotSummary> $summaries
     *
     * @return array{int, int, int, int} estimate days, then leaves to estimate, to assign and to reassign
     */
    private static function sum(array $summaries): array
    {
        $totals = [0, 0, 0, 0];
        foreach ($summaries as $summary) {
            $totals[0] += $summary->estimateDays;
            $totals[1] += $summary->toEstimate;
            $totals[2] += $summary->toAssign;
            $totals[3] += $summary->toReassign;
        }

        return $totals;
    }
}
