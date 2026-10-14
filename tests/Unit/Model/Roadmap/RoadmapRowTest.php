<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Roadmap;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapTeamLine;
use App\Model\Schedule\LeafProgress;
use PHPUnit\Framework\TestCase;

final class RoadmapRowTest extends TestCase
{
    public function testProjectedCostIsTheTimeEnteredPlusWhatTheProgressLeaves(): void
    {
        $project = new Project()->setTitle('Nova');
        $lot = new Lot($project)->setTitle('Sync')->setEstimateDays(20);
        $team = [new RoadmapTeamLine(new User(), 100, 40)];
        $progress = new LeafProgress(40, new \DateTimeImmutable('2026-10-02'), 40, 60);

        $row = new RoadmapRow($project, $lot, remainingQuarters: 60, team: $team, progress: $progress);
        self::assertSame(100, $row->projectedQuarters());
        self::assertSame(20, $row->projectedGapQuarters());
        $withoutProgress = new RoadmapRow($project, $lot, remainingQuarters: 40, team: $team);
        self::assertNull($withoutProgress->projectedQuarters());
        self::assertNull($withoutProgress->projectedGapQuarters());
    }

    public function testUnplannedLeavesAreTheLeavesWithoutStartInTheOrderOfTheChildren(): void
    {
        $project = new Project()->setTitle('Nova');
        $split = new Lot($project)->setTitle('Portail');
        $planned = $this->leaf($project, 'Socle', '2026-10-05', '2026-10-20');
        $withoutStart = $this->leaf($project, 'Sans début');
        $subPlanned = $this->leaf($project, 'Maquettes', '2026-10-05', '2026-10-09', $split);
        $subWithoutStart = $this->leaf($project, 'Recette', parent: $split);
        $splitRow = new RoadmapRow($project, $split, children: [$subPlanned, $subWithoutStart]);
        $projectRow = new RoadmapRow($project, null, children: [$withoutStart, $planned, $splitRow]);

        self::assertSame([$withoutStart], $withoutStart->unplannedLeaves());
        self::assertSame([], $planned->unplannedLeaves());
        self::assertSame([$subWithoutStart], $splitRow->unplannedLeaves());
        self::assertSame([$withoutStart, $subWithoutStart], $projectRow->unplannedLeaves());
    }

    public function testUnknownEndLeavesArePlannedLeavesWithoutEndNeverUnplannedOnes(): void
    {
        $project = new Project()->setTitle('Nova');
        $unknownEnd = $this->leaf($project, 'Dépassée', '2026-10-05');
        $projectRow = new RoadmapRow($project, null, children: [$this->leaf($project, 'Sans début'), $unknownEnd, $this->leaf($project, 'Socle', '2026-10-05', '2026-10-20')]);

        self::assertSame([$unknownEnd], $projectRow->unknownEndLeaves());
    }

    public function testEndCausesAreTheLeavesWithoutEndThenTheUnplannedOnes(): void
    {
        $project = new Project()->setTitle('Nova');
        $unplanned = $this->leaf($project, 'Sans début');
        $unknownEnd = $this->leaf($project, 'Dépassée', '2026-10-05');
        $projectRow = new RoadmapRow($project, null, children: [$unplanned, $this->leaf($project, 'Socle', '2026-10-05', '2026-10-20'), $unknownEnd]);

        self::assertSame([$unknownEnd, $unplanned], $projectRow->endCauses());
    }

    /**
     * @param non-empty-string $title
     */
    private function leaf(Project $project, string $title, ?string $start = null, ?string $end = null, ?Lot $parent = null): RoadmapRow
    {
        return new RoadmapRow(
            $project,
            new Lot($project, $parent)->setTitle($title),
            start: null === $start ? null : new \DateTimeImmutable($start),
            end: null === $end ? null : new \DateTimeImmutable($end),
        );
    }
}
