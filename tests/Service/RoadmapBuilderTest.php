<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Project;
use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Week;
use App\Service\RoadmapBuilder;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class RoadmapBuilderTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    protected function setUp(): void
    {
        self::mockTime('2026-10-07 10:00');
    }

    public function testPlannedLeafShowsWhatWasEnteredThenWhatRemains(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 10, $user), new \DateTimeImmutable('2026-10-05'), [[$user, 100]]);
        $this->createTimeEntry($user, $leaf, '2026-10-05', 4);
        $this->createTimeEntry($user, $leaf, '2026-10-06', 4);

        $row = $this->projectRow($this->build(), $project)->children[0];

        self::assertTrue($row->isLeaf());
        self::assertSame('2026-10-05', $row->start?->format('Y-m-d'));
        self::assertSame('2026-10-19', $row->end?->format('Y-m-d'));
        self::assertNotNull($row->realized);
        self::assertNotNull($row->future);
        self::assertSame(32, $row->remainingQuarters);
        self::assertCount(1, $row->members);
        self::assertSame([], $row->signals);
    }

    public function testSplitLotAndProjectSpanTheirLeavesAndAnUnplannedLeafMakesThePlanningPartial(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $split = $this->createLot($project);
        $this->planLot($this->createLot($project, 5, $user, $split), new \DateTimeImmutable('2026-10-12'), [[$user, 100]]);
        $this->planLot($this->createLot($project, 5, $user, $split), new \DateTimeImmutable('2026-10-19'), [[$user, 100]]);
        $this->createLot($project, 3, $user);

        $row = $this->projectRow($this->build(), $project);

        self::assertSame([RoadmapSignal::PartialPlanning], $row->signals);
        self::assertSame('2026-10-12', $row->start?->format('Y-m-d'));
        self::assertSame('2026-10-23', $row->end?->format('Y-m-d'));
        self::assertNotNull($row->span);
        [$lot, $unplanned] = $row->children;
        self::assertCount(2, $lot->children);
        self::assertSame('2026-10-23', $lot->end?->format('Y-m-d'));
        self::assertNull($unplanned->span);
        self::assertSame([RoadmapSignal::WithoutStart], $unplanned->signals);
    }

    public function testOverrunLeafShowsTheDaysBeyondItsEstimateAndHasNoEnd(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $overrun = $this->planLot($this->createLot($project, 1, $user), new \DateTimeImmutable('2026-10-01'), [[$user, 100]]);
        $this->createTimeEntry($user, $overrun, '2026-10-01', 4);
        $this->createTimeEntry($user, $overrun, '2026-10-02', 2);
        $reached = $this->planLot($this->createLot($project, 1, $user), new \DateTimeImmutable('2026-10-05'), [[$user, 100]]);
        $this->createTimeEntry($user, $reached, '2026-10-05', 4);

        $row = $this->projectRow($this->build(), $project);
        [$overrunRow, $reachedRow] = $row->children;

        self::assertSame([RoadmapSignal::Overrun], $overrunRow->signals);
        self::assertSame(2, $overrunRow->overrunQuarters());
        self::assertSame(50, $overrunRow->overrunPercent());
        self::assertNull($overrunRow->end);
        self::assertTrue($overrunRow->isEndUnknown());
        self::assertSame('2026-10-02', $overrunRow->lastDay?->format('Y-m-d'));
        self::assertNotNull($overrunRow->realized);
        self::assertNotNull($overrunRow->overrun);
        self::assertNull($overrunRow->future);

        self::assertSame([RoadmapSignal::EstimateReached], $reachedRow->signals);
        self::assertSame('2026-10-05', $reachedRow->end?->format('Y-m-d'));
        self::assertNull($reachedRow->overrun);

        self::assertNull($row->end, 'A project with a leaf without end has no end either.');
        self::assertSame('2026-10-05', $row->lastDay?->format('Y-m-d'));
    }

    public function testPlannedLeafWithoutCapacityNorEntryKeepsItsStartAndLeavesTheEndOfItsProjectUnknown(): void
    {
        $user = $this->createUser();
        $former = $this->createUser();
        $project = $this->createProject();
        $this->planLot($this->createLot($project, 5, $user), new \DateTimeImmutable('2026-10-12'), [[$user, 100]]);
        $this->planLot($this->createLot($project, 5), new \DateTimeImmutable('2026-10-19'), [[$former, 100]]);
        $former->setActive(false);
        $this->entityManager()->flush();

        $row = $this->projectRow($this->build(), $project);
        $stranded = $row->children[1];

        self::assertSame('2026-10-19', $stranded->start?->format('Y-m-d'));
        self::assertTrue($stranded->isEndUnknown());
        self::assertSame([RoadmapSignal::TeamToReview], $stranded->signals);
        self::assertSame([], $row->signals, 'The project is fully planned.');
        self::assertNull($row->end);
        self::assertNotNull($row->span);
    }

    public function testProjectWithoutLotIsToSplit(): void
    {
        $project = $this->createProject();

        $row = $this->projectRow($this->build(), $project);

        self::assertSame([RoadmapSignal::Unsplit], $row->signals);
        self::assertNull($row->span);
    }

    public function testOverloadedLeavesAreFlaggedOnlyForPlanningViews(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $this->planLot($this->createLot($project, 10, $user), new \DateTimeImmutable('2026-10-12'), [[$user, 100]]);
        $this->planLot($this->createLot($project, 10, $user), new \DateTimeImmutable('2026-10-19'), [[$user, 50]]);

        $flagged = $this->projectRow($this->build(true), $project)->children;
        $hidden = $this->projectRow($this->build(false), $project)->children;

        foreach ($flagged as $leaf) {
            self::assertTrue($leaf->hasSignal(RoadmapSignal::ToReplan));
        }
        foreach ($hidden as $leaf) {
            self::assertFalse($leaf->hasSignal(RoadmapSignal::ToReplan));
        }
    }

    private function build(bool $withOverloads = true): Roadmap
    {
        $builder = static::getContainer()->get(RoadmapBuilder::class);
        self::assertInstanceOf(RoadmapBuilder::class, $builder);

        return $builder->build(RoadmapWindow::around(Week::fromIso('2026-W41')), $withOverloads);
    }

    private function projectRow(Roadmap $roadmap, Project $project): RoadmapRow
    {
        foreach ($roadmap->projects as $row) {
            if ($row->project->getId() === $project->getId()) {
                return $row;
            }
        }

        self::fail(\sprintf('The project « %s » is not on the roadmap.', $project->getTitle()));
    }
}
