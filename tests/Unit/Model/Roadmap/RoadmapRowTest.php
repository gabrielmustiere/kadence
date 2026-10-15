<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Roadmap;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\RoadmapBar;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapTeamLine;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Schedule\LeafProgress;
use App\Model\Week;
use PHPUnit\Framework\TestCase;

final class RoadmapRowTest extends TestCase
{
    public function testProjectedCostIsTheTimeEnteredPlusWhatTheProgressLeaves(): void
    {
        $project = new Project()->setTitle('Nova');
        $lot = new Lot($project)->setTitle('Sync')->setEstimateDays(20);
        $team = [new RoadmapTeamLine(new User(), 100, 40)];
        $progress = new LeafProgress(40, new \DateTimeImmutable('2026-10-02'));

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

    public function testAFinishedLeafIsCompletedOrHasReachedItsEstimate(): void
    {
        $project = new Project()->setTitle('Nova');

        self::assertTrue($this->leaf($project, 'Socle', signals: [RoadmapSignal::Overrun, RoadmapSignal::Completed])->isFinished());
        self::assertTrue($this->leaf($project, 'Recette', signals: [RoadmapSignal::EstimateReached])->isFinished());
        self::assertFalse($this->leaf($project, 'Doc', signals: [RoadmapSignal::Overrun])->isFinished());
    }

    public function testOnlyKeepsTheUnfinishedLeavesRaisingTheSignalUnderTheirParentsLeftAsTheyWere(): void
    {
        $project = new Project()->setTitle('Nova');
        $split = new Lot($project)->setTitle('Portail');
        $raising = $this->leaf($project, 'Maquettes', signals: [RoadmapSignal::ToEstimate], parent: $split);
        $span = new RoadmapBar(10.0, 30.0, false, false, new \DateTimeImmutable('2026-10-05'), new \DateTimeImmutable('2026-10-20'));
        $splitRow = new RoadmapRow($project, $split, start: new \DateTimeImmutable('2026-10-05'), span: $span, children: [$raising, $this->leaf($project, 'Recette', parent: $split)]);
        $projectRow = new RoadmapRow($project, null, end: new \DateTimeImmutable('2026-11-20'), signals: [RoadmapSignal::PartialPlanning], children: [
            $this->leaf($project, 'Socle', signals: [RoadmapSignal::ToEstimate, RoadmapSignal::Completed]),
            $splitRow,
            $this->leaf($project, 'Doc', signals: [RoadmapSignal::Overrun]),
        ]);

        $only = $projectRow->only(RoadmapSignal::ToEstimate);

        self::assertNotNull($only);
        self::assertCount(1, $only->children);
        $onlySplit = $only->children[0];
        self::assertSame([$raising], $onlySplit->children);
        self::assertSame($split, $onlySplit->lot);
        self::assertSame($span, $onlySplit->span);
        self::assertEquals(new \DateTimeImmutable('2026-11-20'), $only->end);
        self::assertSame([RoadmapSignal::PartialPlanning], $only->signals);
        self::assertCount(3, $projectRow->children, 'The row it is drawn from is left whole.');
        self::assertNull($projectRow->only(RoadmapSignal::TeamToReview));
    }

    public function testOnlyDropsTheProjectsWithoutALeafAwaitingTheSignal(): void
    {
        $nova = new Project()->setTitle('Nova');
        $orion = new Project()->setTitle('Orion');
        $roadmap = new Roadmap(RoadmapWindow::around(Week::fromIso('2026-W42')), new \DateTimeImmutable('2026-10-15'), [
            new RoadmapRow($nova, null, children: [$this->leaf($nova, 'Socle', signals: [RoadmapSignal::LateStart])]),
            new RoadmapRow($orion, null, children: [$this->leaf($orion, 'Recette')]),
            new RoadmapRow($orion, null, signals: [RoadmapSignal::Unsplit]),
        ]);

        $only = $roadmap->only(RoadmapSignal::LateStart);

        self::assertSame([$nova], array_map(static fn (RoadmapRow $row): Project => $row->project, $only->projects));
        self::assertSame($roadmap->window, $only->window);
        self::assertSame($roadmap->today, $only->today);
    }

    /**
     * @param non-empty-string    $title
     * @param list<RoadmapSignal> $signals
     */
    private function leaf(Project $project, string $title, ?string $start = null, ?string $end = null, ?Lot $parent = null, array $signals = []): RoadmapRow
    {
        return new RoadmapRow(
            $project,
            new Lot($project, $parent)->setTitle($title),
            start: null === $start ? null : new \DateTimeImmutable($start),
            end: null === $end ? null : new \DateTimeImmutable($end),
            signals: $signals,
        );
    }
}
