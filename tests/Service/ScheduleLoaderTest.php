<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\Type\HolidayCalendar;
use App\Model\Schedule\DailyCapacity;
use App\Service\ScheduleLoader;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ScheduleLoaderTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    protected function setUp(): void
    {
        self::mockTime('2026-10-07 10:00');
    }

    public function testPlanCarriesTheEstimateTheEntriesTheStartAndTheTeam(): void
    {
        $alice = $this->createUser();
        $bruno = $this->createUser();
        $leaf = $this->createLot($this->createProject(), 10, $alice);
        $this->planLot($leaf, new \DateTimeImmutable('2026-10-05'), [[$alice, 100], [$bruno, 50]]);
        $this->createTimeEntry($alice, $leaf, '2026-10-05', 4);
        $this->createTimeEntry($bruno, $leaf, '2026-10-06', 2);

        $data = $this->loader()->load();
        $plan = $data->plans[(int) $leaf->getId()];

        self::assertSame('2026-10-07', $data->today->format('Y-m-d'));
        self::assertSame(40, $plan->estimateQuarters);
        self::assertSame(6, $plan->consumedQuarters);
        self::assertSame('2026-10-05', $plan->firstEntryDay?->format('Y-m-d'));
        self::assertSame('2026-10-06', $plan->lastEntryDay?->format('Y-m-d'));
        self::assertSame('2026-10-05', $plan->startDate?->format('Y-m-d'));
        self::assertSame([[$alice->getId(), 100], [$bruno->getId(), 50]], array_map(static fn ($member): array => [$member->userId, $member->share], $plan->members));
        self::assertSame($leaf, $data->leaves[(int) $leaf->getId()]);
        self::assertSame($bruno, $data->people[(int) $bruno->getId()]);
    }

    public function testDaysEnteredCountADayOnceWhoeverEnteredTimeOnIt(): void
    {
        $alice = $this->createUser();
        $bruno = $this->createUser();
        $leaf = $this->createLot($this->createProject(), 10, $alice);
        $this->createTimeEntry($alice, $leaf, '2026-10-05', 4);
        $this->createTimeEntry($bruno, $leaf, '2026-10-05', 2);
        $this->createTimeEntry($bruno, $leaf, '2026-10-06', 2);

        $plan = $this->loader()->load()->plans[(int) $leaf->getId()];

        self::assertSame(2, $plan->enteredDayCount);
        self::assertSame(2, $plan->withPlanning(40, null, [])->enteredDayCount);
    }

    public function testOverrunDaysAreTheLastDayWithinTheEstimateAndTheDayTheTimeEnteredWentBeyondIt(): void
    {
        $alice = $this->createUser();
        $bruno = $this->createUser();
        $overrun = $this->createLot($this->createProject(), 1, $alice);
        $this->createTimeEntry($alice, $overrun, '2026-10-05', 3);
        $this->createTimeEntry($bruno, $overrun, '2026-10-05', 1);
        $this->createTimeEntry($alice, $overrun, '2026-10-06', 2);
        $reached = $this->createLot($this->createProject(), 1, $alice);
        $this->createTimeEntry($alice, $reached, '2026-10-05', 4);

        $overrunDays = $this->loader()->load()->overrunDays;

        [$lastDayWithin, $overrunDay] = $overrunDays[(int) $overrun->getId()];
        self::assertSame('2026-10-05', $lastDayWithin?->format('Y-m-d'));
        self::assertSame('2026-10-06', $overrunDay->format('Y-m-d'));
        self::assertArrayNotHasKey((int) $reached->getId(), $overrunDays);
    }

    public function testSplitLotIsNotALeaf(): void
    {
        $project = $this->createProject();
        $split = $this->createLot($project);
        $subLot = $this->createLot($project, parent: $split);

        $data = $this->loader()->load();

        self::assertArrayNotHasKey((int) $split->getId(), $data->plans);
        self::assertArrayHasKey((int) $subLot->getId(), $data->plans);
    }

    public function testCapacityKnowsTheWeeklyMaximumsTheHolidaysAndWhoIsActive(): void
    {
        $partTime = $this->createUser();
        $this->createWeeklyMax($partTime, '2026-10-05', 16);
        $belgian = $this->createUser(holidayCalendar: HolidayCalendar::Belgium);
        $former = $this->createUser();
        $former->setActive(false);
        $this->entityManager()->flush();

        $capacity = $this->loader()->load()->capacity;

        self::assertSame(intdiv(16 * DailyCapacity::UNITS_PER_QUARTER, 5), $capacity->units((int) $partTime->getId(), 100, new \DateTimeImmutable('2026-10-08')));
        self::assertFalse($capacity->isWorkingDay((int) $belgian->getId(), new \DateTimeImmutable('2026-11-11')), 'Armistice is a Belgian holiday.');
        self::assertFalse($capacity->isWorkingDay((int) $partTime->getId(), new \DateTimeImmutable('2026-11-11')), 'Armistice is a French holiday.');
        self::assertTrue($capacity->isWorkingDay((int) $belgian->getId(), new \DateTimeImmutable('2026-07-14')), 'Bastille Day is not a Belgian holiday.');
        self::assertFalse($capacity->isActive((int) $former->getId()));
    }

    public function testHolidaysGoBackToTheFirstDayEntered(): void
    {
        $french = $this->createUser();
        $belgian = $this->createUser(holidayCalendar: HolidayCalendar::Belgium);
        $this->createTimeEntry($french, $this->createLot($this->createProject(), 10, $french), '2026-07-06', 4);

        $capacity = $this->loader()->load()->capacity;

        self::assertFalse($capacity->isWorkingDay((int) $french->getId(), new \DateTimeImmutable('2026-07-14')), 'Bastille Day, after the first day entered.');
        self::assertTrue($capacity->isWorkingDay((int) $belgian->getId(), new \DateTimeImmutable('2026-07-14')));
    }

    public function testHolidaysReachAStartDateNotRecordedYet(): void
    {
        $user = $this->createUser();

        $capacity = $this->loader()->load(new \DateTimeImmutable('2031-01-06'))->capacity;

        self::assertFalse($capacity->isWorkingDay((int) $user->getId(), new \DateTimeImmutable('2033-07-14')), 'Bastille Day 2033, within three years of the start date.');
    }

    private function loader(): ScheduleLoader
    {
        $loader = static::getContainer()->get(ScheduleLoader::class);
        self::assertInstanceOf(ScheduleLoader::class, $loader);

        return $loader;
    }
}
