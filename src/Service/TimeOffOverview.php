<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\TimeOffStatus;
use App\Model\TimeOff\AbsenceMonth;
use App\Model\TimeOff\AbsenceRow;
use App\Model\TimeOff\Balance;
use App\Model\TimeOff\TimeOffDay;
use App\Model\TimeOff\TimeOffDays;
use App\Model\TimeOff\TimeOffPeriod;
use App\Model\TimeOff\TimeOffPreview;
use App\Repository\TimeOffEntitlementRepository;
use App\Repository\TimeOffRequestRepository;

/**
 * Reads the time off of the whole team, each view in a fixed number of queries.
 */
final readonly class TimeOffOverview
{
    public function __construct(
        private TimeOffRequestRepository $timeOffRequestRepository,
        private TimeOffEntitlementRepository $timeOffEntitlementRepository,
        private HolidayManager $holidayManager,
    ) {
    }

    /**
     * The time off of these people over the month of the day: approved for anyone, pending as well for whoever may see
     * their detail.
     *
     * @param list<User> $people
     */
    public function month(User $viewer, \DateTimeImmutable $day, array $people): AbsenceMonth
    {
        $first = AbsenceMonth::firstDayOf($day);
        $last = $first->modify('last day of this month');
        $holidays = $this->holidaysByCalendar($first, $last);

        $requests = self::byUser($this->timeOffRequestRepository->findActiveBetween($first, $last));

        $rows = [];
        foreach ($people as $person) {
            $detail = TimeOffApprovers::canSeeDetailOf($viewer, $person);
            $shown = array_values(array_filter($requests[(int) $person->getId()] ?? [], static fn (TimeOffRequest $request): bool => $detail || TimeOffStatus::Approved === $request->getStatus()));
            $personHolidays = $holidays[$person->getHolidayCalendar()->value];
            $rows[] = new AbsenceRow($person, $detail, $personHolidays, TimeOffDay::collect($shown, $personHolidays, $first, $last));
        }

        return new AbsenceMonth($first, $rows);
    }

    /**
     * The pending requests a person can decide on, each with what it counts, the balance it leaves, and the requests
     * of the other people of the same manager away during it (null without a manager).
     *
     * @return list<array{request: TimeOffRequest, preview: TimeOffPreview, colleagues: list<TimeOffRequest>|null}>
     */
    public function inbox(User $viewer, bool $direction): array
    {
        $requests = $this->timeOffRequestRepository->findPendingDecidableBy($viewer, $direction);
        if ([] === $requests) {
            return [];
        }

        [$from, $to] = self::spanOf($requests);
        $active = $this->timeOffRequestRepository->findActiveBetween($from, $to);
        $holidays = $this->holidaysByCalendar($from, $to);
        $entitled = $this->timeOffEntitlementRepository->findHalfDaysOf(array_map(static fn (TimeOffRequest $request): User => $request->getUser(), $requests));
        $byUser = self::byUser($active);

        return array_map(static function (TimeOffRequest $request) use ($active, $holidays, $entitled, $byUser): array {
            $userId = (int) $request->getUser()->getId();
            $personHolidays = $holidays[$request->getUser()->getHolidayCalendar()->value];
            $balanceOf = static fn (TimeOffPeriod $period): Balance => Balance::count($period, $entitled[$userId][$period->key()] ?? 0, $byUser[$userId] ?? [], $personHolidays);

            return [
                'request' => $request,
                'preview' => TimeOffPreview::of($request->getType(), TimeOffDays::of($request, $personHolidays), $balanceOf, false),
                'colleagues' => self::colleaguesAway($request, $active),
            ];
        }, $requests);
    }

    /**
     * The balance of each of these people over a period.
     *
     * @param list<User> $people
     *
     * @return array<int, Balance> by user id
     */
    public function balancesOver(TimeOffPeriod $period, array $people): array
    {
        $holidays = $this->holidaysByCalendar($period->firstDay(), $period->lastDay());
        $byUser = self::byUser($this->timeOffRequestRepository->findActiveBetween($period->firstDay(), $period->lastDay()));
        $entitlements = $this->timeOffEntitlementRepository->findForPeriod($period);

        $balances = [];
        foreach ($people as $person) {
            $userId = (int) $person->getId();
            $balances[$userId] = Balance::count($period, ($entitlements[$userId] ?? null)?->getHalfDays() ?? 0, $byUser[$userId] ?? [], $holidays[$person->getHolidayCalendar()->value]);
        }

        return $balances;
    }

    /**
     * @return array<string, array<string, non-empty-string>> holiday labels by calendar value, then by day (Y-m-d)
     */
    private function holidaysByCalendar(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $holidays = [];
        foreach (HolidayCalendar::cases() as $calendar) {
            $holidays[$calendar->value] = $this->holidayManager->holidaysBetween($calendar, $from, $to);
        }

        return $holidays;
    }

    /**
     * The first and last days the requests and the periods of their balances reach.
     *
     * @param non-empty-list<TimeOffRequest> $requests
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private static function spanOf(array $requests): array
    {
        $days = [];
        foreach ($requests as $request) {
            $days[] = $request->getStartDay();
            $days[] = $request->getEndDay();
            if ($request->getType()->hasBalance()) {
                $days[] = TimeOffPeriod::containing($request->getType(), $request->getStartDay())->firstDay();
                $days[] = TimeOffPeriod::containing($request->getType(), $request->getEndDay())->lastDay();
            }
        }

        return [min($days), max($days)];
    }

    /**
     * @param list<TimeOffRequest> $requests
     *
     * @return array<int, list<TimeOffRequest>> by user id
     */
    private static function byUser(array $requests): array
    {
        $byUser = [];
        foreach ($requests as $request) {
            $byUser[(int) $request->getUser()->getId()][] = $request;
        }

        return $byUser;
    }

    /**
     * @param list<TimeOffRequest> $away
     *
     * @return list<TimeOffRequest>|null null when the person has no manager, and so no team to look at
     */
    private static function colleaguesAway(TimeOffRequest $request, array $away): ?array
    {
        $manager = $request->getUser()->getManager();
        if (null === $manager) {
            return null;
        }

        return array_values(array_filter($away, static fn (TimeOffRequest $other): bool => $other->getUser() !== $request->getUser()
            && $other->getUser()->getManager()?->getId() === $manager->getId()
            && $other->getStartDay() <= $request->getEndDay()
            && $other->getEndDay() >= $request->getStartDay()));
    }
}
