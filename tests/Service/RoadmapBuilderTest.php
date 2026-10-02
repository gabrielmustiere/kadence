<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\LotInput;
use App\Entity\Project;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\ProjectRoadmap;
use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\RoadmapBar;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapRun;
use App\Model\Roadmap\RoadmapSegment;
use App\Model\Roadmap\RoadmapTeamLine;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Roadmap\TimelineEntry;
use App\Model\Roadmap\TimelineMonth;
use App\Model\Week;
use App\Service\ProjectManager;
use App\Service\RoadmapBuilder;
use App\Tests\Support\CreatesProgress;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class RoadmapBuilderTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesProgress;
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
        self::assertSame(2, $overrunRow->overrunQuarters);
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

    public function testProgressGivesAnOverrunLeafAnEndAndTellsWhatIsCompleteOrToRefresh(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $overrun = $this->planLot($this->createLot($project, 1, $user), new \DateTimeImmutable('2026-10-01'), [[$user, 100]]);
        $this->createTimeEntry($user, $overrun, '2026-10-01', 4);
        $this->createTimeEntry($user, $overrun, '2026-10-02', 2);
        $this->createProgress($overrun, $user, '2026-10-02', 80, 6);
        $complete = $this->planLot($this->createLot($project, 2, $user), new \DateTimeImmutable('2026-10-05'), [[$user, 100]]);
        $this->createTimeEntry($user, $complete, '2026-10-05', 4);
        $this->createProgress($complete, $user, '2026-10-05', 100, 4);
        $toRefresh = $this->planLot($this->createLot($project, 5, $user), new \DateTimeImmutable('2026-10-05'), [[$user, 100]]);
        $this->createTimeEntry($user, $toRefresh, '2026-10-05', 4);
        $this->createProgress($toRefresh, $user, '2026-10-05', 50, 4);
        $this->createTimeEntry($user, $toRefresh, '2026-10-06', 4);

        $row = $this->projectRow($this->build(), $project);
        [$overrunRow, $completeRow, $toRefreshRow] = $row->children;

        self::assertSame([RoadmapSignal::Overrun], $overrunRow->signals);
        self::assertSame(2, $overrunRow->overrunQuarters);
        self::assertSame(2, $overrunRow->remainingQuarters);
        self::assertSame(80, $overrunRow->progress?->percent);
        self::assertSame(8, $overrunRow->projectedQuarters());
        self::assertSame('2026-10-08', $overrunRow->end?->format('Y-m-d'));

        self::assertSame([RoadmapSignal::Completed], $completeRow->signals);
        self::assertSame('2026-10-05', $completeRow->end?->format('Y-m-d'));
        self::assertNull($completeRow->future);

        self::assertSame([RoadmapSignal::ProgressToRefresh], $toRefreshRow->signals);
        self::assertTrue($toRefreshRow->isEndUnknown());
        self::assertNull($row->end, 'A progress to refresh leaves the end of its project unknown.');
    }

    public function testAProgressThatPushesAnEndIntoAnotherLeafOfThePersonIsToReplan(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $declared = $this->planLot($this->createLot($project, 10, $user), new \DateTimeImmutable('2026-10-05'), [[$user, 100]]);
        $this->createTimeEntry($user, $declared, '2026-10-05', 4);
        $this->createTimeEntry($user, $declared, '2026-10-06', 4);
        $next = $this->planLot($this->createLot($project, 5, $user), new \DateTimeImmutable('2026-10-20'), [[$user, 100]]);
        self::assertSame([[], []], array_map(static fn (RoadmapRow $row): array => $row->signals, $this->projectRow($this->build(), $project)->children));

        $this->createProgress($declared, $user, '2026-10-06', 10, 8);

        [$declaredRow, $nextRow] = $this->projectRow($this->build(), $project)->children;
        self::assertSame([RoadmapSignal::ToReplan], $declaredRow->signals);
        self::assertSame([RoadmapSignal::ToReplan], $nextRow->signals);
        self::assertSame((int) $next->getId(), $nextRow->lot?->getId());
    }

    public function testANewDeclarationLiftsTheProgressToRefresh(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 5, $user), new \DateTimeImmutable('2026-10-05'), [[$user, 100]]);
        $this->createTimeEntry($user, $leaf, '2026-10-05', 4);
        $this->createProgress($leaf, $user, '2026-10-05', 50, 4);
        $this->createTimeEntry($user, $leaf, '2026-10-06', 4);
        self::assertSame([RoadmapSignal::ProgressToRefresh], $this->projectRow($this->build(), $project)->children[0]->signals);

        $this->createProgress($leaf, $user, '2026-10-07', 60, 8);

        $row = $this->projectRow($this->build(), $project)->children[0];
        self::assertSame([], $row->signals);
        self::assertSame(6, $row->remainingQuarters);
        self::assertSame('2026-10-09', $row->end?->format('Y-m-d'));
    }

    public function testFirstSubLotTakesOverTheProgressAndTheBarOfItsLot(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $lot = $this->planLot($this->createLot($project, 10, $user), new \DateTimeImmutable('2026-10-05'), [[$user, 100]]);
        $this->createTimeEntry($user, $lot, '2026-10-05', 4);
        $this->createProgress($lot, $user, '2026-10-05', 20, 4);
        $before = $this->projectRow($this->build(), $project)->children[0];

        $input = LotInput::forSubLotOf($lot);
        $input->title = 'Modèle';
        $projectManager = self::getContainer()->get(ProjectManager::class);
        \assert($projectManager instanceof ProjectManager);
        $projectManager->addSubLot($lot, $input);
        // The bulk move leaves the declarations already in memory on their former lot, as a new request would not.
        $this->entityManager()->clear();

        $after = $this->projectRow($this->build(), $project)->children[0]->children[0];
        self::assertSame('Modèle', $after->title());
        self::assertSame(20, $after->progress?->percent);
        self::assertEquals([$before->start, $before->end, $before->remainingQuarters], [$after->start, $after->end, $after->remainingQuarters]);
        self::assertEquals($before->future, $after->future);
    }

    public function testEachRunTellsWhatEachPersonEnteredOnItAndTheDayTheEstimateIsGoneBeyondCountsWholeBeyondIt(): void
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

        [$within] = self::runs($row->realized);
        self::assertSame([4, 1], [$within->quarters, $within->dayCount]);
        self::assertSame([[$alice->getId(), 100, 4], [$bob->getId(), 50, 0]], self::team($within->team));
        [$beyond] = self::runs($row->overrun);
        self::assertSame(['2026-10-02', '2026-10-05'], [$beyond->from->format('Y-m-d'), $beyond->to->format('Y-m-d')]);
        self::assertSame([6, 2], [$beyond->quarters, $beyond->dayCount], 'Bob\'s quarter still within the estimate counts in the run of the day the estimate was gone beyond.');
        self::assertSame(
            [[$alice->getId(), 100, 1], [$bob->getId(), 50, 2], [$carol->getId(), null, 3]],
            self::team($beyond->team),
            'Carol, outside the team, comes after the members.',
        );
        self::assertSame(10, $within->quarters + $beyond->quarters, 'The runs add up to the time entered on the leaf.');
    }

    public function testLeafRecapsWhatWasEnteredOnItWhetherPlannedOrNot(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        $project = $this->createProject();
        $planned = $this->planLot($this->createLot($project, 5, $alice), new \DateTimeImmutable('2026-09-28'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $planned, '2026-09-28', 4);
        $this->createTimeEntry($bob, $planned, '2026-09-28', 2);
        $this->createTimeEntry($alice, $planned, '2026-10-01', 4);
        $unplanned = $this->createLot($project, 5, $alice);
        $this->createTimeEntry($alice, $unplanned, '2026-09-30', 4);

        [$plannedRow, $unplannedRow] = $this->projectRow($this->build(), $project)->children;

        self::assertSame(['2026-09-28', '2026-10-01', 2], [$plannedRow->enteredFrom?->format('Y-m-d'), $plannedRow->enteredTo?->format('Y-m-d'), $plannedRow->enteredDayCount]);
        self::assertSame([[$alice->getId(), 100, 8], [$bob->getId(), null, 2]], self::team($plannedRow->team));
        self::assertSame([RoadmapSignal::WithoutStart], $unplannedRow->signals);
        self::assertNull($unplannedRow->realized);
        self::assertSame(['2026-09-30', '2026-09-30', 1], [$unplannedRow->enteredFrom?->format('Y-m-d'), $unplannedRow->enteredTo?->format('Y-m-d'), $unplannedRow->enteredDayCount]);
        self::assertSame([[$alice->getId(), 100, 4]], self::team($unplannedRow->team));
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
        self::assertSame([[20, 5], [20, 5]], array_map(static fn (RoadmapRun $run): array => [$run->quarters, $run->dayCount], self::runs($row->realized)));
        self::assertSame(10, $row->enteredDayCount);
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
        self::assertSame(5, $row->enteredDayCount);
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
        self::assertSame([2, 2, 1], array_map(static fn (RoadmapRun $run): int => $run->dayCount, [...self::runs($row->realized), ...self::runs($row->overrun)]));
        self::assertSame(5, $row->enteredDayCount);
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

    public function testProjectPageSpansTheDaysOfItsProjectAndListsItsRunsLatestFirst(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        $project = $this->createProject();
        $api = $this->planLot($this->createLot($project, 10, $alice, title: 'API'), new \DateTimeImmutable('2026-09-07'), [[$alice, 100]]);
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'] as $day) {
            $this->createTimeEntry($alice, $api, $day, 4);
        }
        $front = $this->createLot($project, title: 'Front');
        $login = $this->planLot($this->createLot($project, 8, $bob, $front, 'Login'), new \DateTimeImmutable('2026-09-14'), [[$bob, 100]]);
        foreach (['2026-09-14', '2026-09-15', '2026-09-16'] as $day) {
            $this->createTimeEntry($bob, $login, $day, 4);
        }

        $page = $this->buildProject($project);

        self::assertTrue($page->dated);
        self::assertSame(['2026-09-07', '2026-10-18'], [$page->roadmap->window->firstDay()->format('Y-m-d'), $page->roadmap->window->lastDay()->format('Y-m-d')], 'From the first day entered to the end of Login, calculated on 2026-10-14, in whole weeks.');
        self::assertSame([$page->project], $page->roadmap->projects);
        self::assertSame('2026-10-14', $page->project->end?->format('Y-m-d'));
        self::assertCount(1, $page->timeline);
        self::assertSame('Septembre 2026', $page->timeline[0]->label);
        self::assertSame([
            ['API', '2026-09-21', '2026-09-25', 20, 5, false],
            ['Front · Login', '2026-09-14', '2026-09-16', 12, 3, false],
            ['API', '2026-09-07', '2026-09-11', 20, 5, false],
        ], self::entries($page->timeline[0]->entries));
        self::assertSame([[$bob->getId(), 100, 12]], self::team($page->timeline[0]->entries[1]->run->team));
    }

    public function testProjectRowOfItsPageIsTheOneOfTheRoadmapOnTheSameWindow(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 2, $user), new \DateTimeImmutable('2026-09-14'), [[$user, 100]]);
        foreach (['2026-09-14', '2026-09-15', '2026-09-17'] as $day) {
            $this->createTimeEntry($user, $leaf, $day, 4);
        }
        $this->planLot($this->createLot($project, 3, $user), new \DateTimeImmutable('2026-10-12'), [[$user, 50]]);

        $page = $this->buildProject($project);
        $builder = static::getContainer()->get(RoadmapBuilder::class);
        self::assertInstanceOf(RoadmapBuilder::class, $builder);
        $onRoadmap = $this->projectRow($builder->build($page->roadmap->window, true), $project);

        self::assertEquals([$onRoadmap->start, $onRoadmap->end, $onRoadmap->signals, $onRoadmap->span], [$page->project->start, $page->project->end, $page->project->signals, $page->project->span]);
        foreach ($onRoadmap->children as $index => $leafRow) {
            $pageRow = $page->project->children[$index];
            self::assertEquals([$leafRow->realized, $leafRow->overrun, $leafRow->future, $leafRow->signals], [$pageRow->realized, $pageRow->overrun, $pageRow->future, $pageRow->signals]);
        }
    }

    public function testTimelineMarksTheRunsBeyondTheEstimateAndFilesARunUnderTheMonthOfItsLastDay(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 2, $user, title: 'Socle'), new \DateTimeImmutable('2026-08-27'), [[$user, 100]]);
        foreach (['2026-08-27', '2026-08-28', '2026-08-31', '2026-09-01'] as $day) {
            $this->createTimeEntry($user, $leaf, $day, 4);
        }

        $timeline = $this->buildProject($project)->timeline;

        self::assertSame(['Septembre 2026', 'Août 2026'], array_map(static fn (TimelineMonth $month): string => $month->label, $timeline));
        self::assertSame([['Socle', '2026-08-31', '2026-09-01', 8, 2, true]], self::entries($timeline[0]->entries));
        self::assertSame([['Socle', '2026-08-27', '2026-08-28', 8, 2, false]], self::entries($timeline[1]->entries));
    }

    public function testTimeEnteredOnUnplannedLeavesShowsOnTheTimelineButNotOnTheFrieze(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        $project = $this->createProject();
        $withoutStart = $this->createLot($project, 5, $alice, title: 'Sans début');
        foreach (['2026-09-28', '2026-09-29', '2026-10-01'] as $day) {
            $this->createTimeEntry($alice, $withoutStart, $day, 4);
        }
        $withoutTeam = $this->createLot($project, 5, title: 'Sans équipe');
        $this->createTimeEntry($alice, $withoutTeam, '2026-09-28', 2);
        $this->createTimeEntry($bob, $withoutTeam, '2026-09-30', 2);

        $page = $this->buildProject($project);

        self::assertTrue($page->dated);
        self::assertSame(['2026-09-28', '2026-10-25'], [$page->roadmap->window->firstDay()->format('Y-m-d'), $page->roadmap->window->lastDay()->format('Y-m-d')], 'The days entered, on 4 weeks at least.');
        self::assertNull($page->project->children[0]->realized);
        self::assertNull($page->project->children[1]->realized);
        self::assertSame([
            ['Sans début', '2026-10-01', '2026-10-01', 4, 1, false],
            ['Sans équipe', '2026-09-30', '2026-09-30', 2, 1, false],
            ['Sans début', '2026-09-28', '2026-09-29', 8, 2, false],
            ['Sans équipe', '2026-09-28', '2026-09-28', 2, 1, false],
        ], self::entries(array_merge(...array_map(static fn (TimelineMonth $month): array => $month->entries, $page->timeline))), 'Without team, a run is cut on a working day of the people who entered time.');
        self::assertSame(['Octobre 2026', 'Septembre 2026'], array_map(static fn (TimelineMonth $month): string => $month->label, $page->timeline));
    }

    public function testLeafWithoutTeamIsCutOnTheWorkingDaysOfEveryoneWhoEnteredTimeOnIt(): void
    {
        $belgian = $this->createUser(holidayCalendar: HolidayCalendar::Belgium);
        $french = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->createLot($project, 2, title: 'Sans équipe');
        $this->createTimeEntry($belgian, $leaf, '2026-07-20', 4);
        $this->createTimeEntry($belgian, $leaf, '2026-07-22', 4);
        $this->createTimeEntry($french, $leaf, '2026-07-23', 4);

        $timeline = $this->buildProject($project)->timeline;

        self::assertSame([
            ['Sans équipe', '2026-07-23', '2026-07-23', 4, 1, true],
            ['Sans équipe', '2026-07-22', '2026-07-22', 4, 1, false],
            ['Sans équipe', '2026-07-20', '2026-07-20', 4, 1, false],
        ], self::entries($timeline[0]->entries), 'The Belgian national day is a working day for the French person, who entered time beyond the estimate only.');
    }

    public function testProjectWithoutStartNorEntryHasNoFrieze(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $this->createLot($project, 5, $user);

        $page = $this->buildProject($project);

        self::assertFalse($page->dated);
        self::assertSame([], $page->timeline);
        self::assertFalse($this->buildProject($this->createProject())->dated);
    }

    private function buildProject(Project $project): ProjectRoadmap
    {
        $builder = static::getContainer()->get(RoadmapBuilder::class);
        self::assertInstanceOf(RoadmapBuilder::class, $builder);

        return $builder->buildProject($project, true);
    }

    /**
     * @param list<TimelineEntry> $entries
     *
     * @return list<array{string, string, string, int, int, bool}> leaf, first and last day, quarters, days and whether beyond the estimate
     */
    private static function entries(array $entries): array
    {
        return array_map(static fn (TimelineEntry $entry): array => [$entry->leafPath(), $entry->run->from->format('Y-m-d'), $entry->run->to->format('Y-m-d'), $entry->run->quarters, $entry->run->dayCount, $entry->beyondEstimate], $entries);
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
        return array_map(static fn (RoadmapRun $run): array => [$run->from->format('Y-m-d'), $run->to->format('Y-m-d')], self::runs($bar));
    }

    /**
     * @return list<RoadmapRun>
     */
    private static function runs(?RoadmapBar $bar): array
    {
        return array_map(static fn (RoadmapSegment $segment): RoadmapRun => $segment->run, $bar->segments ?? []);
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
