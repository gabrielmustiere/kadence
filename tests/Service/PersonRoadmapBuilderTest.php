<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Lot;
use App\Entity\User;
use App\Enum\Type\RoadmapSignal;
use App\Model\Person\LoadSpan;
use App\Model\Person\PersonLeafRow;
use App\Model\Person\PersonProject;
use App\Model\Person\PersonRoadmap;
use App\Model\Roadmap\RoadmapBar;
use App\Model\Roadmap\RoadmapSegment;
use App\Model\Roadmap\TimelineEntry;
use App\Model\Roadmap\TimelineMonth;
use App\Service\PersonRoadmapBuilder;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class PersonRoadmapBuilderTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    protected function setUp(): void
    {
        self::mockTime('2026-10-02 10:00');
    }

    public function testRowsHoldOnlyTheRunsOfThePersonAndTheirShareOfTheFuture(): void
    {
        [$alice, $leaves] = $this->exampleOfThePitch();

        $page = $this->build($alice);

        self::assertSame([$leaves['login']->getId(), $leaves['api']->getId(), $leaves['maintenance']->getId()], array_map(static fn (PersonLeafRow $row): ?int => $row->lot->getId(), self::rows($page)), 'Projects in the order of the roadmap: Mobile, Refonte, Support.');
        [$login, $api, $maintenance] = self::rows($page);

        self::assertSame([['2026-09-07', '2026-09-11', 20, 5], ['2026-09-21', '2026-09-25', 20, 5]], self::runs($api->realized));
        self::assertSame(100, $api->share);
        self::assertNull($api->future, 'The estimate is reached: no future part.');
        self::assertSame([RoadmapSignal::EstimateReached], $api->signals);

        self::assertSame([['2026-09-14', '2026-09-16', 12, 3]], self::runs($maintenance->realized), 'Bob\'s day on the leaf is not hers.');
        self::assertNull($maintenance->share, 'Outside the team.');
        self::assertNull($maintenance->future);

        self::assertNull($login->realized);
        self::assertSame(50, $login->share);
        self::assertSame(['2026-10-05', '2026-10-26'], [$login->start?->format('Y-m-d'), $login->end?->format('Y-m-d')]);
        self::assertNotNull($login->future);
        self::assertSame('Front · Login', $login->leafPath());
    }

    public function testWindowSpansTheRunsAndTheFuturePartsOfTheirTeams(): void
    {
        [$alice] = $this->exampleOfThePitch();

        $page = $this->build($alice);

        self::assertTrue($page->dated);
        self::assertSame(['2026-09-07', '2026-11-01'], [$page->roadmap->window->firstDay()->format('Y-m-d'), $page->roadmap->window->lastDay()->format('Y-m-d')]);
    }

    public function testProjectsTellWhatThePersonEnteredOnThem(): void
    {
        [$alice] = $this->exampleOfThePitch();

        $page = $this->build($alice);

        self::assertSame(
            [[0, null], [40, '2026-09-25'], [12, '2026-09-16']],
            array_map(static fn (PersonProject $project): array => [$project->enteredQuarters, $project->lastEnteredDay?->format('Y-m-d')], $page->projects),
        );
        self::assertSame([40, 12], array_map(static fn (PersonProject $project): int => $project->enteredQuarters, $page->enteredProjects()), 'Refonte then Support, Mobile having nothing entered.');
    }

    public function testTimelineListsTheRunsOfThePersonFromTheLatest(): void
    {
        [$alice] = $this->exampleOfThePitch();

        $page = $this->build($alice);

        self::assertSame(['Septembre 2026'], array_map(static fn (TimelineMonth $month): string => $month->label, $page->timeline));
        self::assertSame(
            [['API', '2026-09-21', 20], ['Maintenance', '2026-09-14', 12], ['API', '2026-09-07', 20]],
            array_map(static fn (TimelineEntry $entry): array => [$entry->leafPath(), $entry->run->from->format('Y-m-d'), $entry->run->quarters], $page->timeline[0]->entries),
        );
    }

    public function testUpcomingAndLoadOfThePitch(): void
    {
        [$alice, $leaves] = $this->exampleOfThePitch();

        $page = $this->build($alice);

        self::assertSame([$leaves['login']->getId()], array_map(static fn (PersonLeafRow $row): ?int => $row->lot->getId(), $page->upcoming), 'API is over, Maintenance is not hers.');
        $load = $page->load;
        self::assertNotNull($load);
        self::assertSame(['2026-10-05', 50], [$load->nextDay?->format('Y-m-d'), $load->nextDayPercent]);
        self::assertSame('2026-10-27', $load->freeFrom?->format('Y-m-d'));
        self::assertSame([['2026-10-05', '2026-10-26', 50]], array_map(static fn (LoadSpan $span): array => [$span->from->format('Y-m-d'), $span->to->format('Y-m-d'), $span->percent], $load->spans));
    }

    public function testLeavesOfTheirTeamsWithoutEndAreUpcomingAndLeaveTheirFreeDayUnknown(): void
    {
        [$alice, $leaves] = $this->exampleOfThePitch();
        $project = $this->createProject();
        $unplanned = $this->createLot($project, 5, $alice, title: 'Sans début');
        $overrun = $this->planLot($this->createLot($project, 1, $alice, title: 'Dépassée'), new \DateTimeImmutable('2026-09-28'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $overrun, '2026-09-28', 4);
        $this->createTimeEntry($alice, $overrun, '2026-09-29', 2);

        $page = $this->build($alice);

        self::assertSame(
            [$leaves['login']->getId(), $unplanned->getId(), $overrun->getId()],
            array_map(static fn (PersonLeafRow $row): ?int => $row->lot->getId(), $page->upcoming),
            'By first day of the future part, the leaves without one last, in the order of the roadmap.',
        );
        self::assertSame([RoadmapSignal::WithoutStart], $page->upcoming[1]->signals);
        self::assertSame([RoadmapSignal::Overrun], $page->upcoming[2]->signals);
        self::assertNull($page->load?->freeFrom);
        self::assertSame([$unplanned->getId(), $overrun->getId()], array_map(static fn (PersonLeafRow $row): ?int => $row->lot->getId(), $page->load->unknownEnds ?? []));

        $overrunRow = self::rowOf($page, $overrun);
        self::assertSame([['2026-09-28', '2026-09-28', 4, 1]], self::runs($overrunRow->realized));
        self::assertSame([['2026-09-29', '2026-09-29', 2, 1]], self::runs($overrunRow->overrun), 'Beyond the estimate of the leaf.');
    }

    public function testRunsAreCutOnTheWorkingDaysOfThePersonAlone(): void
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        $leaf = $this->planLot($this->createLot($this->createProject(), 10, $alice), new \DateTimeImmutable('2026-09-28'), [[$alice, 50], [$bob, 50]]);
        $this->createTimeEntry($alice, $leaf, '2026-09-28', 2);
        $this->createTimeEntry($bob, $leaf, '2026-09-29', 2);
        $this->createTimeEntry($alice, $leaf, '2026-09-30', 2);

        $row = self::rowOf($this->build($alice), $leaf);

        self::assertSame([['2026-09-28', '2026-09-28', 2, 1], ['2026-09-30', '2026-09-30', 2, 1]], self::runs($row->realized), 'Bob entered on 29/09, Alice did not.');
    }

    public function testOverloadedLeavesAreFlaggedOnPlanningViewsOnly(): void
    {
        $alice = $this->createUser();
        $project = $this->createProject();
        $first = $this->planLot($this->createLot($project, 5, $alice), new \DateTimeImmutable('2026-10-05'), [[$alice, 100]]);
        $this->planLot($this->createLot($project, 5, $alice), new \DateTimeImmutable('2026-10-05'), [[$alice, 100]]);

        self::assertContains(RoadmapSignal::ToReplan, self::rowOf($this->build($alice, withOverloads: true), $first)->signals);
        self::assertNotContains(RoadmapSignal::ToReplan, self::rowOf($this->build($alice), $first)->signals);
        self::assertSame([200], array_map(static fn (LoadSpan $span): int => $span->percent, $this->build($alice)->load->spans ?? []));
        self::assertTrue(($this->build($alice)->load->spans[0] ?? null)?->isOverload());
    }

    public function testLoadIsLeftOutWhenNotToBeShownOrWhenThePersonIsDeactivated(): void
    {
        [$alice] = $this->exampleOfThePitch();

        self::assertNull($this->build($alice, withLoad: false)->load);

        $alice->setActive(false);
        $this->entityManager()->flush();
        $page = $this->build($alice);
        self::assertNull($page->load);
        self::assertCount(1, $page->upcoming, 'The leaves of their teams stay listed.');
    }

    public function testPersonWithNothingHasNoFrieze(): void
    {
        $page = $this->build($this->createUser());

        self::assertFalse($page->dated);
        self::assertSame([], $page->projects);
        self::assertSame([], $page->upcoming);
        self::assertSame([], $page->timeline);
        self::assertSame([], $page->load->spans ?? null);
        self::assertNull($page->load?->freeFrom);
    }

    /**
     * Alice, at 100 % on « API » (Refonte, 10 j) entered in full on 07/09–11/09 and 21/09–25/09, on « Maintenance »
     * (Support) outside its team on 14/09–16/09, alongside Bob on 15/09, and at 50 % on « Login » (Mobile, lot Front,
     * 8 j) from 05/10.
     *
     * @return array{User, array{api: Lot, maintenance: Lot, login: Lot}}
     */
    private function exampleOfThePitch(): array
    {
        $alice = $this->createUser();
        $bob = $this->createUser();

        $api = $this->planLot($this->createLot($this->createProject(uniqid('Refonte ', true)), 10, $alice, title: 'API'), new \DateTimeImmutable('2026-09-07'), [[$alice, 100]]);
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'] as $day) {
            $this->createTimeEntry($alice, $api, $day, 4);
        }

        $maintenance = $this->createLot($this->createProject(uniqid('Support ', true)), 20, title: 'Maintenance');
        foreach (['2026-09-14', '2026-09-15', '2026-09-16'] as $day) {
            $this->createTimeEntry($alice, $maintenance, $day, 4);
        }
        $this->createTimeEntry($bob, $maintenance, '2026-09-15', 4);

        $mobile = $this->createProject(uniqid('Mobile ', true));
        $login = $this->planLot($this->createLot($mobile, 8, parent: $this->createLot($mobile, title: 'Front'), title: 'Login'), new \DateTimeImmutable('2026-10-05'), [[$alice, 50]]);

        return [$alice, ['api' => $api, 'maintenance' => $maintenance, 'login' => $login]];
    }

    private function build(User $person, bool $withOverloads = false, bool $withLoad = true): PersonRoadmap
    {
        $builder = static::getContainer()->get(PersonRoadmapBuilder::class);
        \assert($builder instanceof PersonRoadmapBuilder);

        return $builder->build($person, $withOverloads, $withLoad);
    }

    /**
     * @return list<PersonLeafRow>
     */
    private static function rows(PersonRoadmap $page): array
    {
        return array_merge(...array_map(static fn (PersonProject $project): array => $project->rows, $page->projects));
    }

    private static function rowOf(PersonRoadmap $page, Lot $leaf): PersonLeafRow
    {
        foreach (self::rows($page) as $row) {
            if ($row->lot->getId() === $leaf->getId()) {
                return $row;
            }
        }

        self::fail('No row for the leaf.');
    }

    /**
     * @return list<array{string, string, int, int}> first and last day, quarters and days entered of each run
     */
    private static function runs(?RoadmapBar $bar): array
    {
        return array_map(static fn (RoadmapSegment $segment): array => [$segment->run->from->format('Y-m-d'), $segment->run->to->format('Y-m-d'), $segment->run->quarters, $segment->run->dayCount], $bar->segments ?? []);
    }
}
