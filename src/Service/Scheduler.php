<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Schedule\DailyCapacity;
use App\Model\Schedule\LeafPlan;
use App\Model\Schedule\LeafSchedule;
use App\Model\Schedule\PlannedMember;
use App\Model\Schedule\ScheduleResult;

/**
 * Works out the chronology of each leaf: the past is the time entered, the future spreads the remaining time over the
 * capacity of the team, working day after working day, from tomorrow or from the start date if later.
 */
final readonly class Scheduler
{
    public const int HORIZON_DAYS = 3 * 366;

    /**
     * @param list<LeafPlan> $plans
     */
    public function schedule(array $plans, DailyCapacity $capacity, \DateTimeImmutable $today): ScheduleResult
    {
        $result = new ScheduleResult();
        foreach ($plans as $plan) {
            $schedule = $this->scheduleLeaf($plan, $capacity, $today);
            $result->add($schedule, $this->loadOf($plan, $schedule, $capacity));
        }

        return $result;
    }

    public function scheduleLeaf(LeafPlan $plan, DailyCapacity $capacity, \DateTimeImmutable $today): LeafSchedule
    {
        $today = $today->setTime(0, 0);
        $remaining = null === $plan->estimateQuarters ? null : $plan->estimateQuarters - $plan->consumedQuarters;
        $teamToReview = array_any($plan->members, static fn (PlannedMember $member): bool => !$capacity->isActive($member->userId));

        $schedule = static fn (?\DateTimeImmutable $futureFrom = null, ?\DateTimeImmutable $futureTo = null, bool $lateStart = false, bool $exhausted = false, bool $toReview = false): LeafSchedule => new LeafSchedule(
            $plan->lotId,
            $plan->firstEntryDay,
            $plan->lastEntryDay,
            $futureFrom,
            $futureTo,
            $remaining,
            null === $remaining,
            null === $plan->startDate,
            [] === $plan->members,
            $lateStart,
            $exhausted,
            $teamToReview || $toReview,
        );

        if (null === $remaining || null === $plan->startDate || [] === $plan->members) {
            return $schedule();
        }

        $lateStart = $plan->startDate < $today && null === $plan->firstEntryDay;
        if ($remaining <= 0) {
            return $schedule(lateStart: $lateStart, exhausted: true);
        }

        $day = max($plan->startDate->setTime(0, 0), $today->modify('+1 day'));
        $left = $remaining * DailyCapacity::UNITS_PER_QUARTER;
        $first = null;
        for ($i = 0; $i < self::HORIZON_DAYS; ++$i, $day = $day->modify('+1 day')) {
            $units = 0;
            foreach ($plan->members as $member) {
                $units += $capacity->units($member->userId, $member->share, $day);
            }
            if (0 === $units) {
                continue;
            }

            $first ??= $day;
            $left -= $units;
            if ($left <= 0) {
                return $schedule($first, $day, $lateStart);
            }
        }

        return $schedule($first, null, $lateStart, toReview: true);
    }

    /**
     * The share each active member gives the leaf on each of their working days of its future part.
     *
     * @return array<int, array<string, int>> share by user id and day (Y-m-d)
     */
    public function loadOf(LeafPlan $plan, LeafSchedule $schedule, DailyCapacity $capacity): array
    {
        if (null === $schedule->futureFrom || null === $schedule->futureTo) {
            return [];
        }

        $load = [];
        $last = $schedule->futureTo->format('Y-m-d');
        for ($day = $schedule->futureFrom; $day->format('Y-m-d') <= $last; $day = $day->modify('+1 day')) {
            foreach ($plan->members as $member) {
                if ($capacity->isActive($member->userId) && $capacity->isWorkingDay($member->userId, $day)) {
                    $load[$member->userId][$day->format('Y-m-d')] = $member->share;
                }
            }
        }

        return $load;
    }
}
