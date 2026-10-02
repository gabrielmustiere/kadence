<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Schedule\DailyCapacity;
use App\Model\Schedule\LeafPlan;
use App\Model\Schedule\LeafSchedule;
use App\Model\Schedule\PlannedMember;
use App\Model\Schedule\ScheduleData;
use App\Model\Schedule\ScheduleResult;

/**
 * Works out the chronology of each leaf: the past is the time entered, the future spreads the remaining time over the
 * capacity of the team, working day after working day, from tomorrow or from the start date if later. The remaining
 * time follows the progress declared on the leaf, if any.
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

    public function scheduleAll(ScheduleData $data): ScheduleResult
    {
        return $this->schedule(array_values($data->plans), $data->capacity, $data->today);
    }

    public function scheduleLeaf(LeafPlan $plan, DailyCapacity $capacity, \DateTimeImmutable $today): LeafSchedule
    {
        $today = $today->setTime(0, 0);
        $remaining = self::remainingOf($plan);
        $overrun = null === $plan->estimateQuarters ? 0 : max(0, $plan->consumedQuarters - $plan->estimateQuarters);
        $teamToReview = array_any($plan->members, static fn (PlannedMember $member): bool => !$capacity->isActive($member->userId));

        $schedule = static fn (?\DateTimeImmutable $futureFrom = null, ?\DateTimeImmutable $futureTo = null, bool $lateStart = false, bool $exhausted = false, bool $toReview = false): LeafSchedule => new LeafSchedule(
            lotId: $plan->lotId,
            realizedFrom: $plan->firstEntryDay,
            realizedTo: $plan->lastEntryDay,
            futureFrom: $futureFrom,
            futureTo: $futureTo,
            remainingQuarters: $remaining,
            toEstimate: null === $remaining,
            withoutStart: null === $plan->startDate,
            withoutTeam: [] === $plan->members,
            lateStart: $lateStart,
            exhausted: $exhausted,
            teamToReview: $teamToReview || $toReview,
            overrunQuarters: $overrun,
            progress: $plan->progress,
        );

        if (null === $remaining || null === $plan->startDate || [] === $plan->members) {
            return $schedule();
        }

        $lateStart = $plan->startDate < $today && null === $plan->firstEntryDay;
        if (0 === $remaining) {
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
     * What is left to do: from the progress in force when one is declared, else the estimate minus the consumed time.
     */
    private static function remainingOf(LeafPlan $plan): ?int
    {
        if (null === $plan->estimateQuarters) {
            return null;
        }

        return max(0, $plan->progress?->remainingAfter($plan->consumedQuarters) ?? $plan->estimateQuarters - $plan->consumedQuarters);
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
