<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\TimeEntry;
use App\Enum\Type\HalfDay;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\TimeOffStatus;
use App\Enum\Type\TimeOffType;
use App\Model\TimeOff\TimeOffPeriod;
use App\Repository\TimeEntryRepository;
use App\Repository\TimeOffRequestRepository;
use App\Service\HolidayManager;
use App\Service\TimeOffBook;
use App\Tests\Support\CreatesTimeOff;
use App\Tests\Support\CreatesUsers;
use App\Tests\Support\PurgesHolidayAdjustments;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TimeOffBookTest extends KernelTestCase
{
    use CreatesTimeOff;
    use CreatesUsers;
    use PurgesHolidayAdjustments;

    public function testTheDaysOfAPersonAreThoseOfTheirPendingAndApprovedRequests(): void
    {
        $user = $this->createUser();
        $this->createTimeOff($user, '2026-11-09', '2026-11-13');
        $this->createTimeOff($user, '2026-11-16', '2026-11-16', TimeOffStatus::Pending, TimeOffType::Rtt, HalfDay::Afternoon);
        $this->createTimeOff($user, '2026-11-17', '2026-11-17', TimeOffStatus::Refused);
        $this->createTimeOff($user, '2026-11-18', '2026-11-18', TimeOffStatus::Cancelled);

        $days = $this->book()->daysOf($user, new \DateTimeImmutable('2026-11-09'), new \DateTimeImmutable('2026-11-20'));

        self::assertSame(['2026-11-09', '2026-11-10', '2026-11-12', '2026-11-13', '2026-11-16'], array_keys($days));
        self::assertSame('Congé · CP', $days['2026-11-09']->label());
        self::assertSame('Congé · RTT (après-midi, en attente)', $days['2026-11-16']->label());
        self::assertSame(2, $days['2026-11-16']->capQuarters());
    }

    public function testTheDaysOfAPersonStayWithinTheRangeAsked(): void
    {
        $user = $this->createUser();
        $this->createTimeOff($user, '2026-11-09', '2026-11-20');

        $days = $this->book()->daysOf($user, new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-11-17'));

        self::assertSame(['2026-11-16', '2026-11-17'], array_keys($days));
    }

    public function testTheBalanceCountsApprovedAndPendingDaysOfItsTypeAndPeriod(): void
    {
        $user = $this->createUser();
        $period = TimeOffPeriod::of(TimeOffType::PaidLeave, 2026);
        $this->createEntitlement($user, TimeOffType::PaidLeave, 2026, 50);
        $this->createTimeOff($user, '2026-11-09', '2026-11-13');
        $this->createTimeOff($user, '2026-11-16', '2026-11-17', TimeOffStatus::Pending);
        $this->createTimeOff($user, '2026-11-18', '2026-11-18', TimeOffStatus::Approved, TimeOffType::Rtt);
        $this->createTimeOff($user, '2026-11-19', '2026-11-19', TimeOffStatus::Refused);

        $balance = $this->book()->balanceOf($user, $period);

        self::assertSame(50, $balance->entitledHalfDays);
        self::assertSame(8, $balance->approvedHalfDays);
        self::assertSame(4, $balance->pendingHalfDays);
        self::assertSame(38, $balance->remainingHalfDays());
    }

    public function testAPersonWithoutEntitlementsHasNoDay(): void
    {
        $balance = $this->book()->balanceOf($this->createUser(), TimeOffPeriod::of(TimeOffType::Rtt, 2026));

        self::assertSame(0, $balance->entitledHalfDays);
        self::assertSame(0, $balance->remainingHalfDays());
    }

    public function testADayOffThatBecomesAHolidayNoLongerCounts(): void
    {
        $user = $this->createUser();
        $period = TimeOffPeriod::of(TimeOffType::PaidLeave, 2030);
        $this->createTimeOff($user, '2031-03-10', '2031-03-12');
        self::assertSame(6, $this->book()->balanceOf($user, $period)->approvedHalfDays);

        $holidayManager = static::getContainer()->get(HolidayManager::class);
        \assert($holidayManager instanceof HolidayManager);
        $holidayManager->add(HolidayCalendar::France, new \DateTimeImmutable('2031-03-11'), 'Remplacement');

        self::assertSame(4, $this->book()->balanceOf($user, $period)->approvedHalfDays);
    }

    public function testAPreviewSplitsANewRequestOverItsPeriodsAndCountsItAsPending(): void
    {
        $user = $this->createUser();
        $this->createEntitlement($user, TimeOffType::PaidLeave, 2026, 50);
        $this->createTimeOff($user, '2026-11-09', '2026-11-13');

        $preview = $this->book()->preview($user, TimeOffType::PaidLeave, new \DateTimeImmutable('2027-04-29'), HalfDay::Morning, new \DateTimeImmutable('2027-05-04'), HalfDay::Afternoon);

        self::assertSame(8, $preview->halfDays);
        self::assertCount(2, $preview->periods);
        [$counted, $balance] = $preview->periods[0];
        self::assertSame(4, $counted);
        self::assertSame('2026-2027', $balance->period->label());
        self::assertSame(38, $balance->remainingHalfDays());
        [$counted, $balance] = $preview->periods[1];
        self::assertSame(4, $counted);
        self::assertSame(-4, $balance->remainingHalfDays());
        self::assertTrue($preview->isOverrun());
    }

    public function testAPreviewOfATypeWithoutBalanceOnlyCountsTheDays(): void
    {
        $preview = $this->book()->preview($this->createUser(), TimeOffType::Unpaid, new \DateTimeImmutable('2026-11-16'), HalfDay::Afternoon, new \DateTimeImmutable('2026-11-18'), HalfDay::Morning);

        self::assertSame(4, $preview->halfDays);
        self::assertSame([], $preview->periods);
        self::assertFalse($preview->isOverrun());
    }

    public function testTheBalancesAreThoseOfTheCurrentPeriodsAndOfThePeriodsToComeARequestFallsIn(): void
    {
        $user = $this->createUser();
        $this->createTimeOff($user, '2027-12-30', '2028-01-03', TimeOffStatus::Pending, TimeOffType::Rtt);
        $this->createTimeOff($user, '2025-03-03', '2025-03-04', TimeOffStatus::Approved, TimeOffType::PaidLeave);

        $balances = $this->book()->balancesOf($user, new \DateTimeImmutable('2026-10-12'));

        self::assertSame(['cp 2026-2027', 'rtt 2026', 'rtt 2027', 'rtt 2028'], array_map(static fn ($balance): string => $balance->period->type->value . ' ' . $balance->period->label(), $balances));
    }

    public function testTheBalancesCountTheRequestsAndTheEntitlementsOfEachPeriod(): void
    {
        $user = $this->createUser();
        $this->createEntitlement($user, TimeOffType::PaidLeave, 2026, 50);
        $this->createEntitlement($user, TimeOffType::Rtt, 2026, 20);
        $this->createTimeOff($user, '2026-11-09', '2026-11-13');
        $this->createTimeOff($user, '2026-11-16', '2026-11-16', TimeOffStatus::Pending, TimeOffType::Rtt);

        $balances = $this->book()->balancesOf($user, new \DateTimeImmutable('2026-10-12'));

        self::assertSame([[50, 8, 0], [20, 0, 2]], array_map(static fn ($balance): array => [$balance->entitledHalfDays, $balance->approvedHalfDays, $balance->pendingHalfDays], $balances));
    }

    public function testTheHistoryCountsTheHalfDaysOfEachRequest(): void
    {
        $user = $this->createUser();
        $this->createTimeOff($user, '2026-11-09', '2026-11-13');
        $this->createTimeOff($user, '2026-11-16', '2026-11-16', TimeOffStatus::Refused, TimeOffType::Rtt, HalfDay::Afternoon, HalfDay::Afternoon);

        self::assertSame([1, 8], array_map(static fn (array $item): int => $item['halfDays'], $this->book()->historyOf($user)));
    }

    public function testTheDemoCompanyEntersNoTimeOnADayOff(): void
    {
        $requests = static::getContainer()->get(TimeOffRequestRepository::class);
        \assert($requests instanceof TimeOffRequestRepository);
        $entries = static::getContainer()->get(TimeEntryRepository::class);
        \assert($entries instanceof TimeEntryRepository);

        $active = $requests->findActiveBetween(new \DateTimeImmutable('-1 year'), new \DateTimeImmutable('+1 year'));
        self::assertNotEmpty($active, 'The demo company has time off.');
        foreach ($active as $request) {
            $days = array_keys($this->book()->daysOfRequest($request)->halves);
            $entered = array_filter(
                $entries->findForUserBetween($request->getUser(), $request->getStartDay(), $request->getEndDay()),
                static fn (TimeEntry $entry): bool => \in_array($entry->getDay()->format('Y-m-d'), $days, true),
            );
            self::assertSame([], $entered, \sprintf('%s entered time on a day off.', $request->getUser()->getEmail()));
        }
    }

    private function book(): TimeOffBook
    {
        $book = static::getContainer()->get(TimeOffBook::class);
        \assert($book instanceof TimeOffBook);

        return $book;
    }
}
