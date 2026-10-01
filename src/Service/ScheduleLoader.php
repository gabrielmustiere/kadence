<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\User;
use App\Enum\Type\HolidayCalendar;
use App\Model\Quarters;
use App\Model\Schedule\DailyCapacity;
use App\Model\Schedule\LeafPlan;
use App\Model\Schedule\PlannedMember;
use App\Model\Schedule\ScheduleData;
use App\Repository\LotRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\UserRepository;
use App\Repository\WeeklyMaxRepository;
use Psr\Clock\ClockInterface;

/**
 * Gathers, in a fixed number of queries, what the scheduler needs for every leaf.
 */
final readonly class ScheduleLoader
{
    public function __construct(
        private LotRepository $lotRepository,
        private TimeEntryRepository $timeEntryRepository,
        private WeeklyMaxRepository $weeklyMaxRepository,
        private UserRepository $userRepository,
        private HolidayManager $holidayManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param \DateTimeImmutable|null $startingAt a start date not recorded yet, whose holidays are needed as well
     */
    public function load(?\DateTimeImmutable $startingAt = null): ScheduleData
    {
        $today = $this->clock->now()->setTime(0, 0);
        $summaries = $this->timeEntryRepository->summarizeByLot();

        $leaves = [];
        $plans = [];
        $firstDay = $today->modify('monday this week');
        $lastStart = max($today, $startingAt ?? $today);
        foreach ($this->lotRepository->findLeavesForSchedule() as $leaf) {
            $lotId = (int) $leaf->getId();
            $leaves[$lotId] = $leaf;
            $plans[$lotId] = self::planOf($leaf, $summaries[$lotId] ?? null);
            $firstDay = min($firstDay, $plans[$lotId]->firstEntryDay ?? $firstDay);
            $lastStart = max($lastStart, $leaf->getStartDate() ?? $today);
        }

        $people = [];
        foreach ($this->userRepository->findAllForTeamList() as $user) {
            $people[(int) $user->getId()] = $user;
        }

        return new ScheduleData($leaves, $plans, $people, $this->capacity($people, $firstDay, $lastStart), $today, $this->overrunDays($plans));
    }

    /**
     * @param iterable<array{User, int<25, 100>}> $members person and share
     *
     * @return list<PlannedMember>
     */
    public static function plannedMembers(iterable $members): array
    {
        $planned = [];
        foreach ($members as [$user, $share]) {
            $planned[] = new PlannedMember((int) $user->getId(), $share);
        }

        return $planned;
    }

    /**
     * @param array<int, LeafPlan> $plans
     *
     * @return array<int, array{\DateTimeImmutable|null, \DateTimeImmutable}> last day entered within the estimate, then day on
     *                                                                        which the time entered went beyond it, by lot id
     */
    private function overrunDays(array $plans): array
    {
        $overrun = array_filter($plans, static fn (LeafPlan $plan): bool => null !== $plan->estimateQuarters && $plan->consumedQuarters > $plan->estimateQuarters);

        $days = [];
        foreach ($this->timeEntryRepository->sumQuartersByDayForLots(array_keys($overrun)) as $lotId => $quartersByDay) {
            $days[$lotId] = self::overrunDaysOf($overrun[$lotId], $quartersByDay);
        }

        return array_filter($days);
    }

    /**
     * @param array<string, int> $quartersByDay quarters entered each day (Y-m-d, in date order)
     *
     * @return array{\DateTimeImmutable|null, \DateTimeImmutable}|null
     */
    private static function overrunDaysOf(LeafPlan $plan, array $quartersByDay): ?array
    {
        $entered = 0;
        $lastDayWithin = null;
        foreach ($quartersByDay as $day => $quarters) {
            $entered += $quarters;
            if ($entered > $plan->estimateQuarters) {
                return [null === $lastDayWithin ? null : new \DateTimeImmutable($lastDayWithin), new \DateTimeImmutable($day)];
            }
            $lastDayWithin = $day;
        }

        return null;
    }

    /**
     * @param array{int, string, string}|null $summary quarters entered, first and last day entered
     */
    private static function planOf(Lot $leaf, ?array $summary): LeafPlan
    {
        $estimateDays = $leaf->getEstimateDays();

        return new LeafPlan(
            (int) $leaf->getId(),
            null === $estimateDays ? null : $estimateDays * Quarters::PER_DAY,
            $summary[0] ?? 0,
            null === $summary ? null : new \DateTimeImmutable($summary[1]),
            null === $summary ? null : new \DateTimeImmutable($summary[2]),
            $leaf->getStartDate(),
            self::plannedMembers(array_map(static fn (LotMember $member): array => [$member->getUser(), $member->getShare()], $leaf->getMembers()->getValues())),
        );
    }

    /**
     * @param array<int, User>   $people
     * @param \DateTimeImmutable $firstDay the Monday of the current week, or the first day entered on a leaf if earlier
     */
    private function capacity(array $people, \DateTimeImmutable $firstDay, \DateTimeImmutable $lastStart): DailyCapacity
    {
        $until = $lastStart->modify(\sprintf('+%d days', Scheduler::HORIZON_DAYS + 7));

        $holidays = [];
        foreach (HolidayCalendar::cases() as $calendar) {
            $holidays[$calendar->value] = array_fill_keys(array_keys($this->holidayManager->holidaysBetween($calendar, $firstDay, $until)), true);
        }

        return new DailyCapacity(
            array_map(static fn (User $user): array => [$user->getHolidayCalendar(), $user->isActive()], $people),
            $this->weeklyMaxRepository->findAllQuartersByUser(),
            $holidays,
        );
    }
}
