<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\Type\HolidayCalendar;
use App\Model\Schedule\DailyCapacity;
use App\Model\Schedule\LeafPlan;
use App\Model\Schedule\LeafProgress;
use App\Model\Schedule\LeafSchedule;
use App\Model\Schedule\PlannedMember;
use App\Service\Scheduler;
use PHPUnit\Framework\TestCase;

/**
 * Week S starts on Monday 2026-10-05, a week without holiday; « today » is the Friday before unless told otherwise.
 */
final class SchedulerTest extends TestCase
{
    private const int ALICE = 1;
    private const int BRUNO = 2;
    private const int FORMER = 3;
    private const string TODAY = '2026-10-02';
    private const string MONDAY = '2026-10-05';

    public function testTenDaysForOneFullTimePersonEndOnTheFridayOfTheNextWeek(): void
    {
        $schedule = $this->scheduleOf($this->plan(10, [self::ALICE => 100]));

        $this->assertFuture('2026-10-05', '2026-10-16', $schedule);
        self::assertSame(40, $schedule->remainingQuarters);
        self::assertTrue($schedule->isPlanned());
    }

    public function testTwoFullTimePeopleEndOnTheFridayOfTheSameWeek(): void
    {
        $this->assertFuture('2026-10-05', '2026-10-09', $this->scheduleOf($this->plan(10, [self::ALICE => 100, self::BRUNO => 100])));
    }

    public function testHalfShareEndsOnTheFridayOfTheFourthWeek(): void
    {
        $this->assertFuture('2026-10-05', '2026-10-30', $this->scheduleOf($this->plan(10, [self::ALICE => 50])));
    }

    public function testHolidayOfAMemberPushesTheEnd(): void
    {
        $schedule = $this->scheduleOf($this->plan(10, [self::ALICE => 100]), $this->capacity(holidays: ['fr' => ['2026-10-07' => true]]));

        $this->assertFuture('2026-10-05', '2026-10-19', $schedule);
    }

    public function testLowerWeeklyMaximumPushesTheEnd(): void
    {
        $schedule = $this->scheduleOf($this->plan(10, [self::ALICE => 100]), $this->capacity(weeklyMaxes: [self::ALICE => [['2026-01-05', 16]]]));

        $this->assertFuture('2026-10-05', '2026-10-21', $schedule);
    }

    public function testSlowerEntryPushesTheEndFromTomorrow(): void
    {
        $plan = $this->plan(10, [self::ALICE => 100], consumedQuarters: 12, firstEntry: '2026-10-05', lastEntry: '2026-10-07');

        $schedule = $this->scheduleOf($plan, today: '2026-10-09');

        $this->assertFuture('2026-10-12', '2026-10-20', $schedule);
        self::assertSame('2026-10-05', $schedule->start()?->format('Y-m-d'));
        self::assertSame('2026-10-07', $schedule->realizedTo?->format('Y-m-d'));
        self::assertFalse($schedule->lateStart);
    }

    public function testPastStartWithoutAnyEntryStartsTomorrowAndIsLate(): void
    {
        $schedule = $this->scheduleOf($this->plan(10, [self::ALICE => 100]), today: '2026-10-07');

        $this->assertFuture('2026-10-08', '2026-10-21', $schedule);
        self::assertTrue($schedule->lateStart);
        self::assertNull($schedule->realizedFrom);
    }

    public function testStartOnAWeekendBeginsOnTheNextWorkingDay(): void
    {
        $schedule = $this->scheduleOf($this->plan(1, [self::ALICE => 100], start: '2026-10-03'));

        $this->assertFuture('2026-10-05', '2026-10-05', $schedule);
    }

    public function testEntriesBeforeTheStartMakeTheBarBeginOnTheFirstDayEntered(): void
    {
        $plan = $this->plan(10, [self::ALICE => 100], consumedQuarters: 4, firstEntry: '2026-09-28', lastEntry: '2026-09-28', start: '2026-10-12');

        $schedule = $this->scheduleOf($plan);

        self::assertSame('2026-09-28', $schedule->start()?->format('Y-m-d'));
        $this->assertFuture('2026-10-12', '2026-10-22', $schedule);
    }

    public function testReachedEstimateEndsOnTheLastDayEntered(): void
    {
        $plan = $this->plan(2, [self::ALICE => 100], consumedQuarters: 8, firstEntry: '2026-09-28', lastEntry: '2026-09-29', start: '2026-09-28');

        $schedule = $this->scheduleOf($plan);

        self::assertTrue($schedule->isEstimateReached());
        self::assertFalse($schedule->isOverrun());
        self::assertFalse($schedule->hasFuture());
        self::assertSame('2026-09-29', $schedule->end()?->format('Y-m-d'));
    }

    public function testOverrunEstimateHasNoEndUntilItIsRevised(): void
    {
        $plan = $this->plan(2, [self::ALICE => 100], consumedQuarters: 10, firstEntry: '2026-09-28', lastEntry: '2026-10-01', start: '2026-09-28');

        $schedule = $this->scheduleOf($plan);

        self::assertTrue($schedule->isOverrun());
        self::assertFalse($schedule->isEstimateReached());
        self::assertFalse($schedule->hasFuture());
        self::assertSame(0, $schedule->remainingQuarters);
        self::assertSame(2, $schedule->overrunQuarters);
        self::assertNull($schedule->end());
        self::assertSame('2026-09-28', $schedule->start()?->format('Y-m-d'));
    }

    public function testProgressExtrapolatesTheRemainingAndPushesTheEndByFiveWorkingDays(): void
    {
        $withoutProgress = $this->scheduleOf($this->plan(20, [self::ALICE => 100], consumedQuarters: 40, firstEntry: '2026-09-14', lastEntry: '2026-09-25'));
        $withProgress = $this->scheduleOf($this->plan(20, [self::ALICE => 100], consumedQuarters: 40, firstEntry: '2026-09-14', lastEntry: '2026-09-25', progress: $this->progress(40, 40, 60)));

        $this->assertFuture('2026-10-05', '2026-10-16', $withoutProgress);
        $this->assertFuture('2026-10-05', '2026-10-23', $withProgress);
        self::assertSame(60, $withProgress->remainingQuarters);
    }

    public function testTimeEnteredSinceTheProgressUsesUpWhatIsLeft(): void
    {
        $schedule = $this->scheduleOf($this->plan(20, [self::ALICE => 100], consumedQuarters: 52, firstEntry: '2026-09-14', lastEntry: '2026-10-01', progress: $this->progress(40, 40, 60)));

        self::assertSame(48, $schedule->remainingQuarters);
        $this->assertFuture('2026-10-05', '2026-10-20', $schedule);
    }

    public function testRevisedEstimateDoesNotMoveALeafWithAProgress(): void
    {
        $progress = $this->progress(40, 40, 60);

        $this->assertFuture('2026-10-05', '2026-10-23', $this->scheduleOf($this->plan(30, [self::ALICE => 100], consumedQuarters: 40, firstEntry: '2026-09-14', lastEntry: '2026-09-25', progress: $progress)));
    }

    public function testOverrunLeafWithAProgressGetsAnEnd(): void
    {
        $schedule = $this->scheduleOf($this->plan(10, [self::ALICE => 100], consumedQuarters: 48, firstEntry: '2026-09-14', lastEntry: '2026-10-01', progress: $this->progress(80, 48, 12)));

        self::assertTrue($schedule->isOverrun());
        self::assertSame(8, $schedule->overrunQuarters);
        self::assertSame(12, $schedule->remainingQuarters);
        $this->assertFuture('2026-10-05', '2026-10-07', $schedule);
        self::assertSame('2026-10-07', $schedule->end()?->format('Y-m-d'));
    }

    public function testLeafDeclaredCompleteEndsOnItsLastDayEntered(): void
    {
        $schedule = $this->scheduleOf($this->plan(20, [self::ALICE => 100], consumedQuarters: 40, firstEntry: '2026-09-14', lastEntry: '2026-10-01', start: '2026-09-14', progress: $this->progress(100, 40, 0)));

        self::assertTrue($schedule->isCompleted());
        self::assertFalse($schedule->isEstimateReached());
        self::assertFalse($schedule->isProgressToRefresh());
        self::assertFalse($schedule->hasFuture());
        self::assertSame('2026-10-01', $schedule->end()?->format('Y-m-d'));
    }

    public function testProgressUsedUpIsToRefreshAndHasNoEnd(): void
    {
        $schedule = $this->scheduleOf($this->plan(20, [self::ALICE => 100], consumedQuarters: 44, firstEntry: '2026-09-14', lastEntry: '2026-10-01', start: '2026-09-14', progress: $this->progress(50, 20, 20)));

        self::assertTrue($schedule->isProgressToRefresh());
        self::assertFalse($schedule->isEstimateReached());
        self::assertFalse($schedule->isCompleted());
        self::assertSame(0, $schedule->remainingQuarters);
        self::assertNull($schedule->end());
    }

    public function testDeactivatedMemberNoLongerCountsAndTheTeamIsToReview(): void
    {
        $schedule = $this->scheduleOf($this->plan(10, [self::ALICE => 100, self::FORMER => 100]));

        $this->assertFuture('2026-10-05', '2026-10-16', $schedule);
        self::assertTrue($schedule->teamToReview);
    }

    public function testTeamWithoutCapacityHasNoEnd(): void
    {
        $schedule = $this->scheduleOf($this->plan(10, [self::FORMER => 100]));

        self::assertFalse($schedule->hasFuture());
        self::assertNull($schedule->end());
        self::assertTrue($schedule->teamToReview);
    }

    public function testRemainingTimeNotCoveredWithinTheHorizonHasNoEnd(): void
    {
        $schedule = $this->scheduleOf($this->plan(2000, [self::ALICE => 25]), $this->capacity(weeklyMaxes: [self::ALICE => [['2026-01-05', 1]]]));

        self::assertNull($schedule->end());
        self::assertTrue($schedule->teamToReview);
    }

    public function testLeafMissingAnEstimateAStartOrATeamIsNotPlanned(): void
    {
        $toEstimate = $this->scheduleOf($this->plan(null, [self::ALICE => 100]));
        $withoutStart = $this->scheduleOf($this->plan(10, [self::ALICE => 100], start: null));
        $withoutTeam = $this->scheduleOf($this->plan(10, []));

        self::assertTrue($toEstimate->toEstimate);
        self::assertTrue($withoutStart->withoutStart);
        self::assertTrue($withoutTeam->withoutTeam);
        foreach ([$toEstimate, $withoutStart, $withoutTeam] as $schedule) {
            self::assertFalse($schedule->isPlanned());
            self::assertFalse($schedule->hasFuture());
        }
    }

    public function testLoadAddsUpTheSharesOfOverlappingLeavesAndFlagsTheOverload(): void
    {
        $result = new Scheduler()->schedule([
            $this->plan(10, [self::ALICE => 100], lotId: 1),
            $this->plan(10, [self::ALICE => 50], lotId: 2, start: '2026-10-12'),
            $this->plan(10, [self::BRUNO => 100], lotId: 3),
        ], $this->capacity(), new \DateTimeImmutable(self::TODAY));

        self::assertSame(150, $result->loadAt(self::ALICE, '2026-10-12'));
        self::assertSame(100, $result->loadAt(self::ALICE, '2026-10-05'));
        self::assertSame(0, $result->loadAt(self::ALICE, '2026-10-10'), 'No load on a weekend.');
        self::assertSame([1, 2], $result->contributorsAt(self::ALICE, '2026-10-12'));
        self::assertSame([1 => true, 2 => true], $result->overloadedLots());
    }

    public function testHolidayOfAPersonCarriesNoLoad(): void
    {
        $result = new Scheduler()->schedule([
            $this->plan(10, [self::ALICE => 100], lotId: 1),
            $this->plan(10, [self::ALICE => 100], lotId: 2),
        ], $this->capacity(holidays: ['fr' => ['2026-10-07' => true]]), new \DateTimeImmutable(self::TODAY));

        self::assertSame(0, $result->loadAt(self::ALICE, '2026-10-07'));
        self::assertSame(200, $result->loadAt(self::ALICE, '2026-10-08'));
    }

    /**
     * @param array<int, int<25, 100>> $members share by user id
     */
    private function plan(?int $estimateDays, array $members, int $consumedQuarters = 0, ?string $firstEntry = null, ?string $lastEntry = null, ?string $start = self::MONDAY, int $lotId = 1, ?LeafProgress $progress = null): LeafPlan
    {
        return new LeafPlan(
            $lotId,
            null === $estimateDays ? null : $estimateDays * 4,
            $consumedQuarters,
            null === $firstEntry ? null : new \DateTimeImmutable($firstEntry),
            null === $lastEntry ? null : new \DateTimeImmutable($lastEntry),
            null === $start ? null : new \DateTimeImmutable($start),
            array_map(static fn (int $userId, int $share): PlannedMember => new PlannedMember($userId, $share), array_keys($members), array_values($members)),
            progress: $progress,
        );
    }

    /**
     * @param int<1, 100> $percent
     * @param int<0, max> $enteredQuarters
     * @param int<0, max> $remainingQuarters
     */
    private function progress(int $percent, int $enteredQuarters, int $remainingQuarters): LeafProgress
    {
        return new LeafProgress($percent, new \DateTimeImmutable('2026-09-25'), $enteredQuarters, $remainingQuarters);
    }

    private function scheduleOf(LeafPlan $plan, ?DailyCapacity $capacity = null, string $today = self::TODAY): LeafSchedule
    {
        return new Scheduler()->scheduleLeaf($plan, $capacity ?? $this->capacity(), new \DateTimeImmutable($today));
    }

    /**
     * @param array<int, list<array{string, int<1, 20>}>> $weeklyMaxes
     * @param array<string, array<string, true>>          $holidays
     */
    private function capacity(array $weeklyMaxes = [], array $holidays = []): DailyCapacity
    {
        return new DailyCapacity(
            [self::ALICE => [HolidayCalendar::France, true], self::BRUNO => [HolidayCalendar::France, true], self::FORMER => [HolidayCalendar::France, false]],
            $weeklyMaxes,
            $holidays,
        );
    }

    private function assertFuture(string $from, string $to, LeafSchedule $schedule): void
    {
        self::assertSame($from, $schedule->futureFrom?->format('Y-m-d'));
        self::assertSame($to, $schedule->futureTo?->format('Y-m-d'));
        self::assertSame($to, $schedule->end()?->format('Y-m-d'));
    }
}
