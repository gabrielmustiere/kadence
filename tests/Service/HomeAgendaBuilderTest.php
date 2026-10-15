<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Lot;
use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\RoadmapSignal;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Model\Home\HomeAgenda;
use App\Model\Home\WatchedProject;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Timesheet\TimesheetDay;
use App\Service\HomeAgendaBuilder;
use App\Tests\Support\CreatesProgress;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesTimeOff;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class HomeAgendaBuilderTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesProgress;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesTimeOff;
    use CreatesUsers;

    protected function setUp(): void
    {
        self::mockTime('2026-10-14 10:00');
    }

    public function testTheForgottenDaysAreThoseOfThePreviousWeekAndOfThePastDaysOfThisOneOldestFirst(): void
    {
        $user = $this->createUser();
        $this->createTimeEntry($user, $this->createLot($this->createProject()), '2026-10-06', 4);

        self::assertSame(
            ['2026-10-05', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-12', '2026-10-13'],
            array_map(static fn (TimesheetDay $day): string => $day->date->format('Y-m-d'), $this->build($user)->forgottenDays),
        );
    }

    public function testALeafCallingForItsOwnerIsAmongTheirLeavesOnly(): void
    {
        $owner = $this->createUser();
        $overrun = $this->overrunLeaf($owner);
        $toEstimate = $this->createLot($overrun->getProject(), owner: $owner, title: 'Sans estimation');
        $withoutStart = $this->createLot($overrun->getProject(), 5, $owner, title: 'Sans début');

        $leaves = $this->leavesOf($this->build($owner)->myLeaves);

        self::assertContains($overrun, $leaves);
        self::assertContains($toEstimate, $leaves);
        self::assertNotContains($withoutStart, $leaves);
        self::assertSame([], $this->leavesOf($this->build($this->createUser())->myLeaves));
        $row = array_find($this->build($owner)->myLeaves, static fn (RoadmapRow $row): bool => $row->lot?->getId() === $overrun->getId());
        self::assertNotNull($row);
        self::assertTrue($row->hasSignal(RoadmapSignal::Overrun));
    }

    public function testAProjectWhoseEndIsUnknownIsWatchedByTheLeadsOnly(): void
    {
        $leaf = $this->overrunLeaf($this->createUser());
        $calm = $this->planLot($this->createLot($this->createProject(), 50), new \DateTimeImmutable('2026-10-19'), [[$this->createUser(), 100]])->getProject();
        $lead = $this->createUser(Role::Lead);

        $watched = $this->watchedOf($this->build($lead, withProjects: true));

        self::assertArrayHasKey((int) $leaf->getProject()->getId(), $watched);
        self::assertTrue($watched[(int) $leaf->getProject()->getId()]->row->isEndUnknown());
        self::assertSame([RoadmapSignal::Overrun], $watched[(int) $leaf->getProject()->getId()]->signals);
        self::assertArrayNotHasKey((int) $calm->getId(), $watched);
        self::assertSame([], $this->build($lead)->watchedProjects);
    }

    public function testALeafDeclaredDoneBeyondItsEstimateCallsForNobody(): void
    {
        $owner = $this->createUser();
        $leaf = $this->overrunLeaf($owner);
        $this->createProgress($leaf, $owner, '2026-10-07', 100);
        $lead = $this->createUser(Role::Lead);

        $row = array_find($this->build($lead, withProjects: true)->watchedProjects, static fn (WatchedProject $project): bool => $project->row->project === $leaf->getProject());

        self::assertNotContains($leaf, $this->leavesOf($this->build($owner)->myLeaves));
        self::assertNull($row, 'Its project has nothing else to watch.');
    }

    public function testTheRequestsOfThePersonArePendingOnesThenThoseSomeoneElseSettledInTheLastSevenDays(): void
    {
        $user = $this->createUser();
        $manager = $this->createUser(Role::Lead);
        $pending = $this->createTimeOff($user, '2026-12-14', '2026-12-14', TimeOffStatus::Pending);
        $refusedLately = $this->settle($this->createTimeOff($user, '2026-11-02', '2026-11-02', TimeOffStatus::Pending), static fn (TimeOffRequest $request): TimeOffRequest => $request->refuse($manager, new \DateTimeImmutable('2026-10-08 09:00'), 'Charge'));
        $this->settle($this->createTimeOff($user, '2026-11-03', '2026-11-03', TimeOffStatus::Pending), static fn (TimeOffRequest $request): TimeOffRequest => $request->refuse($manager, new \DateTimeImmutable('2026-10-06 09:00'), 'Charge'));
        $approvedLately = $this->settle($this->createTimeOff($user, '2026-11-04', '2026-11-04', TimeOffStatus::Pending), static fn (TimeOffRequest $request): TimeOffRequest => $request->approve($manager, new \DateTimeImmutable('2026-10-13 09:00')));
        $cancelledByTheManager = $this->settle($this->createTimeOff($user, '2026-11-05', '2026-11-05', TimeOffStatus::Pending), static fn (TimeOffRequest $request): TimeOffRequest => $request->cancel($manager, new \DateTimeImmutable('2026-10-12 09:00')));
        $this->settle($this->createTimeOff($user, '2026-11-06', '2026-11-06', TimeOffStatus::Pending), static fn (TimeOffRequest $request): TimeOffRequest => $request->cancel($user, new \DateTimeImmutable('2026-10-12 09:00')));
        $this->settle($this->createTimeOff($user, '2026-11-09', '2026-11-09', TimeOffStatus::Pending), static fn (TimeOffRequest $request): TimeOffRequest => $request->approve(null, new \DateTimeImmutable('2026-10-13 09:00')));

        self::assertSame([$pending, $refusedLately, $approvedLately, $cancelledByTheManager], $this->build($user)->myRequests);
    }

    public function testTheRequestsToDecideAreThoseOfTheReportsOfAPersonWhoDecides(): void
    {
        $manager = $this->createUser(Role::Lead);
        $request = $this->createTimeOff($this->createUser()->setManager($manager), '2026-11-16', '2026-11-20', TimeOffStatus::Pending);

        self::assertSame([$request], $this->build($manager, canDecide: true)->toDecide);
        self::assertSame([], $this->build($manager)->toDecide);
    }

    public function testAPersonWithNothingToDoHasAnEmptyAgenda(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());
        $this->createWeeklyMax($user, '2026-10-05', 1);
        $this->createTimeEntry($user, $lot, '2026-10-05', 1);
        $this->createTimeEntry($user, $lot, '2026-10-12', 1);

        $agenda = $this->build($user);

        self::assertTrue($agenda->isEmpty());
        self::assertFalse($this->build($this->createUser())->isEmpty());
    }

    private function build(User $user, bool $canDecide = false, bool $withProjects = false): HomeAgenda
    {
        $builder = self::getContainer()->get(HomeAgendaBuilder::class);
        \assert($builder instanceof HomeAgendaBuilder);

        return $builder->build($user, $canDecide, false, $withProjects);
    }

    /**
     * A planned leaf of a new project whose estimate its owner went beyond.
     */
    private function overrunLeaf(User $owner): Lot
    {
        $leaf = $this->planLot($this->createLot($this->createProject(), 1, $owner, title: 'En dépassement'), new \DateTimeImmutable('2026-10-05'), [[$owner, 100]]);
        $this->createTimeEntry($owner, $leaf, '2026-10-05', 4);
        $this->createTimeEntry($owner, $leaf, '2026-10-06', 4);

        return $leaf;
    }

    /**
     * @param \Closure(TimeOffRequest): TimeOffRequest $decision
     */
    private function settle(TimeOffRequest $request, \Closure $decision): TimeOffRequest
    {
        $decision($request);
        $this->entityManager()->flush();

        return $request;
    }

    /**
     * @param list<RoadmapRow> $rows
     *
     * @return list<Lot>
     */
    private function leavesOf(array $rows): array
    {
        return array_values(array_filter(array_map(static fn (RoadmapRow $row): ?Lot => $row->lot, $rows)));
    }

    /**
     * @return array<int, WatchedProject> by project id
     */
    private function watchedOf(HomeAgenda $agenda): array
    {
        $watched = [];
        foreach ($agenda->watchedProjects as $project) {
            $watched[(int) $project->row->project->getId()] = $project;
        }

        return $watched;
    }
}
