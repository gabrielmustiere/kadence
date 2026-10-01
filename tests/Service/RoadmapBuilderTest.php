<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Project;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\RoadmapBar;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapTeamLine;
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

    public function testEachPersonsTimeFillsTheEstimateInTheOrderItWasEnteredThenGoesBeyondIt(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        $carol = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 2, $alice), new \DateTimeImmutable('2026-10-01'), [[$alice, 100], [$bob, 50]]);
        $this->createTimeEntry($alice, $leaf, '2026-10-01', 4);
        $this->createTimeEntry($carol, $leaf, '2026-10-02', 3);
        $this->createTimeEntry($bob, $leaf, '2026-10-02', 2);
        $this->createTimeEntry($alice, $leaf, '2026-10-05', 1);

        $row = $this->projectRow($this->build(), $project)->children[0];

        self::assertSame(
            [[$alice->getId(), 100, 4], [$bob->getId(), 50, 1], [$carol->getId(), null, 3]],
            self::team($row->realizedTeam),
            'Bob\'s entry reaches the estimate: one quarter of it is within, the other beyond.',
        );
        self::assertSame(
            [[$alice->getId(), 100, 1], [$bob->getId(), 50, 1]],
            self::team($row->overrunTeam),
            'Carol, outside the team, entered nothing beyond the estimate.',
        );
    }

    public function testTimeEnteredIsCutWhereAWeekGoesByWithoutEntry(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 10, $user), new \DateTimeImmutable('2026-09-07'), [[$user, 100]]);
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'] as $day) {
            $this->createTimeEntry($user, $leaf, $day, 4);
        }

        $row = $this->projectRow($this->build(), $project)->children[0];

        self::assertSame([['2026-09-07', '2026-09-11'], ['2026-09-21', '2026-09-25']], self::segments($row->realized));
        self::assertNotNull($row->realized);
        self::assertSame('2026-09-07', $row->realized->from->format('Y-m-d'));
        self::assertSame('2026-09-25', $row->realized->to->format('Y-m-d'));
        self::assertSame(10, $row->realizedDayCount);
        self::assertSame([RoadmapSignal::EstimateReached], $row->signals);
    }

    public function testAWorkingDayWithoutEntryCutsTheTimeEnteredButAWeekendDoesNot(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 10, $user), new \DateTimeImmutable('2026-09-14'), [[$user, 100]]);
        foreach (['2026-09-14', '2026-09-15', '2026-09-17', '2026-09-18', '2026-09-21'] as $day) {
            $this->createTimeEntry($user, $leaf, $day, 4);
        }

        $row = $this->projectRow($this->build(), $project)->children[0];

        self::assertSame([['2026-09-14', '2026-09-15'], ['2026-09-17', '2026-09-21']], self::segments($row->realized));
        self::assertSame(5, $row->realizedDayCount);
    }

    public function testAHolidayCutsTheTimeEnteredOnlyWhenOneMemberCouldWork(): void
    {
        $french = $this->createUser();
        $belgian = $this->createUser(holidayCalendar: HolidayCalendar::Belgium);
        $former = $this->createUser();
        $project = $this->createProject();
        $teams = [
            'everyone off on Ascension' => [[$french, 100], [$belgian, 50]],
            'Belgian national day, French member' => [[$french, 50], [$belgian, 50]],
            'Belgian national day, Belgian team' => [[$belgian, 50]],
            'Bastille Day, French team' => [[$french, 50]],
            'Belgian national day, former French member' => [[$former, 100], [$belgian, 25]],
        ];
        $days = [
            'everyone off on Ascension' => ['2026-05-13', '2026-05-15'],
            'Belgian national day, French member' => ['2026-07-20', '2026-07-22'],
            'Belgian national day, Belgian team' => ['2026-07-20', '2026-07-22'],
            'Bastille Day, French team' => ['2026-07-13', '2026-07-15'],
            'Belgian national day, former French member' => ['2026-07-20', '2026-07-22'],
        ];
        foreach ($teams as $title => $members) {
            $leaf = $this->planLot($this->createLot($project, 10, title: $title), new \DateTimeImmutable('2026-05-04'), $members);
            foreach ($days[$title] as $day) {
                $this->createTimeEntry($belgian, $leaf, $day, 2);
            }
        }
        $former->setActive(false);
        $this->entityManager()->flush();

        $segments = [];
        foreach ($this->projectRow($this->build(anchor: '2026-W22'), $project)->children as $row) {
            $segments[$row->title()] = \count($row->realized->segments ?? []);
        }

        self::assertSame([
            'everyone off on Ascension' => 1,
            'Belgian national day, French member' => 2,
            'Belgian national day, Belgian team' => 1,
            'Bastille Day, French team' => 1,
            'Belgian national day, former French member' => 2,
        ], $segments);
    }

    public function testTimeEnteredBeyondTheEstimateIsCutTheSameWay(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 2, $user), new \DateTimeImmutable('2026-09-14'), [[$user, 100]]);
        foreach (['2026-09-14', '2026-09-15', '2026-09-17', '2026-09-18', '2026-09-22'] as $day) {
            $this->createTimeEntry($user, $leaf, $day, 4);
        }

        $row = $this->projectRow($this->build(), $project)->children[0];

        self::assertSame([['2026-09-14', '2026-09-15']], self::segments($row->realized));
        self::assertSame([['2026-09-17', '2026-09-18'], ['2026-09-22', '2026-09-22']], self::segments($row->overrun));
        self::assertSame(2, $row->realizedDayCount);
        self::assertSame(3, $row->overrunDayCount);
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

    private function build(bool $withOverloads = true, string $anchor = '2026-W41'): Roadmap
    {
        $builder = static::getContainer()->get(RoadmapBuilder::class);
        self::assertInstanceOf(RoadmapBuilder::class, $builder);

        return $builder->build(RoadmapWindow::around(Week::fromIso($anchor)), $withOverloads);
    }

    /**
     * @return list<array{string, string}> first and last day of each segment
     */
    private static function segments(?RoadmapBar $bar): array
    {
        return array_map(static fn (RoadmapBar $segment): array => [$segment->from->format('Y-m-d'), $segment->to->format('Y-m-d')], $bar->segments ?? []);
    }

    /**
     * @param list<RoadmapTeamLine> $team
     *
     * @return list<array{int|null, int|null, int}> user id, share and quarters entered
     */
    private static function team(array $team): array
    {
        return array_map(static fn (RoadmapTeamLine $line): array => [$line->user->getId(), $line->share, $line->quarters], $team);
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
