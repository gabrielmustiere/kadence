<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\HalfDay;
use App\Enum\Type\TimeOffType;
use App\Model\TimeOff\Balance;
use App\Model\TimeOff\TimeOffDay;
use App\Model\TimeOff\TimeOffDays;
use App\Model\TimeOff\TimeOffPeriod;
use App\Model\TimeOff\TimeOffPreview;
use App\Repository\TimeOffEntitlementRepository;
use App\Repository\TimeOffRequestRepository;

/**
 * Reads the time off: its days are always worked out on the holiday calendar in force.
 */
final readonly class TimeOffBook
{
    public function __construct(
        private TimeOffRequestRepository $timeOffRequestRepository,
        private TimeOffEntitlementRepository $timeOffEntitlementRepository,
        private HolidayManager $holidayManager,
    ) {
    }

    /**
     * The pending and approved time off of the person on each of their working days between two days included.
     *
     * @param array<string, string>|null $holidays the holidays of the person between the two days, when already known
     *
     * @return array<string, TimeOffDay> by day (Y-m-d)
     */
    public function daysOf(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to, ?array $holidays = null): array
    {
        $holidays ??= $this->holidayManager->holidaysBetween($user->getHolidayCalendar(), $from, $to);

        return TimeOffDay::collect($this->timeOffRequestRepository->findActiveOfUserBetween($user, $from, $to), $holidays, $from, $to);
    }

    /**
     * The approved time off of everyone, in half-days by user id and weekday, holidays included: the capacity leaves
     * the holidays out on its own.
     *
     * @return array<int, array<string, int<1, 2>>>
     */
    public function approvedHalfDaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $halfDays = [];
        foreach ($this->timeOffRequestRepository->findApprovedBetween($from, $to) as $request) {
            $userId = (int) $request->getUser()->getId();
            foreach (TimeOffDays::of($request)->halves as $day => $halves) {
                $halfDays[$userId][$day] = min(2, ($halfDays[$userId][$day] ?? 0) + \count($halves));
            }
        }

        return $halfDays;
    }

    /**
     * The half-days the request counts, on the working days of its person.
     */
    public function daysOfRequest(TimeOffRequest $request): TimeOffDays
    {
        return TimeOffDays::of($request, $this->holidayManager->holidaysBetween($request->getUser()->getHolidayCalendar(), $request->getStartDay(), $request->getEndDay()));
    }

    /**
     * Every request of the person, the latest start first, with the half-days each counts.
     *
     * @return list<array{request: TimeOffRequest, halfDays: int}>
     */
    public function historyOf(User $user): array
    {
        $requests = $this->timeOffRequestRepository->findOfUser($user);
        if ([] === $requests) {
            return [];
        }

        $holidays = $this->holidayManager->holidaysBetween(
            $user->getHolidayCalendar(),
            min(array_map(static fn (TimeOffRequest $request): \DateTimeImmutable => $request->getStartDay(), $requests)),
            max(array_map(static fn (TimeOffRequest $request): \DateTimeImmutable => $request->getEndDay(), $requests)),
        );

        return array_map(static fn (TimeOffRequest $request): array => ['request' => $request, 'halfDays' => TimeOffDays::of($request, $holidays)->halfDayCount()], $requests);
    }

    public function balanceOf(User $user, TimeOffPeriod $period): Balance
    {
        return Balance::count(
            $period,
            $this->timeOffEntitlementRepository->findOneFor($user, $period)?->getHalfDays() ?? 0,
            $this->timeOffRequestRepository->findActiveOfUserBetween($user, $period->firstDay(), $period->lastDay()),
            $this->holidayManager->holidaysBetween($user->getHolidayCalendar(), $period->firstDay(), $period->lastDay()),
        );
    }

    /**
     * The balances of the current periods, then of the periods to come a pending or approved request falls in.
     *
     * @return list<Balance> by type, then by period
     */
    public function balancesOf(User $user, \DateTimeImmutable $today): array
    {
        $requests = $this->timeOffRequestRepository->findActiveOfUser($user);
        $periods = [];
        foreach (TimeOffType::withBalance() as $type) {
            $current = TimeOffPeriod::containing($type, $today);
            $periods[$current->key()] = $current;
        }
        foreach ($requests as $request) {
            if (!$request->getType()->hasBalance()) {
                continue;
            }
            foreach ([$request->getStartDay(), $request->getEndDay()] as $bound) {
                $period = TimeOffPeriod::containing($request->getType(), $bound);
                if ($period->lastDay() >= $today->setTime(0, 0)) {
                    $periods[$period->key()] = $period;
                }
            }
        }
        ksort($periods);

        $from = $today;
        $to = $today;
        foreach ($periods as $period) {
            $from = min($from, $period->firstDay());
            $to = max($to, $period->lastDay());
        }
        $holidays = $this->holidayManager->holidaysBetween($user->getHolidayCalendar(), $from, $to);
        $entitled = $this->timeOffEntitlementRepository->findHalfDaysOf([$user])[(int) $user->getId()] ?? [];

        return array_values(array_map(static fn (TimeOffPeriod $period): Balance => Balance::count($period, $entitled[$period->key()] ?? 0, $requests, $holidays), $periods));
    }

    /**
     * What a new request would count, and the balance it would leave on each period it falls in.
     */
    public function preview(User $user, TimeOffType $type, \DateTimeImmutable $startDay, HalfDay $startHalf, \DateTimeImmutable $endDay, HalfDay $endHalf): TimeOffPreview
    {
        return $this->previewOf($user, $type, $this->requestedDays($user, $startDay, $startHalf, $endDay, $endHalf), true);
    }

    /**
     * The half-days a new request of the person would count, on their working days.
     */
    public function requestedDays(User $user, \DateTimeImmutable $startDay, HalfDay $startHalf, \DateTimeImmutable $endDay, HalfDay $endHalf): TimeOffDays
    {
        return TimeOffDays::between($startDay, $startHalf, $endDay, $endHalf, $this->holidayManager->holidaysBetween($user->getHolidayCalendar(), $startDay, $endDay));
    }

    /**
     * What a recorded request counts, and the balance left on each period it falls in, the request included.
     */
    public function previewOfRequest(TimeOffRequest $request): TimeOffPreview
    {
        return $this->previewOf($request->getUser(), $request->getType(), $this->daysOfRequest($request), false);
    }

    private function previewOf(User $user, TimeOffType $type, TimeOffDays $days, bool $isNew): TimeOffPreview
    {
        return TimeOffPreview::of($type, $days, fn (TimeOffPeriod $period): Balance => $this->balanceOf($user, $period), $isNew);
    }
}
