<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\TimeOff;

use App\Enum\Type\TimeOffType;
use App\Model\TimeOff\TimeOffPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeOffPeriodTest extends TestCase
{
    /**
     * @return iterable<string, array{TimeOffType, string, string}>
     */
    public static function periods(): iterable
    {
        yield 'paid leave on April 30th' => [TimeOffType::PaidLeave, '2027-04-30', '2026-2027'];
        yield 'paid leave on May 1st' => [TimeOffType::PaidLeave, '2027-05-01', '2027-2028'];
        yield 'paid leave in November' => [TimeOffType::PaidLeave, '2026-11-09', '2026-2027'];
        yield 'RTT on December 31st' => [TimeOffType::Rtt, '2026-12-31', '2026'];
        yield 'RTT on January 1st' => [TimeOffType::Rtt, '2027-01-01', '2027'];
    }

    #[DataProvider('periods')]
    public function testAPeriodContainsItsDays(TimeOffType $type, string $day, string $label): void
    {
        $period = TimeOffPeriod::containing($type, new \DateTimeImmutable($day));

        self::assertSame($label, $period->label());
        self::assertTrue($period->contains(new \DateTimeImmutable($day)));
    }

    public function testThePaidLeaveRunsFromMayToApril(): void
    {
        $period = TimeOffPeriod::of(TimeOffType::PaidLeave, 2026);

        self::assertSame('2026-05-01', $period->firstDay()->format('Y-m-d'));
        self::assertSame('2027-04-30', $period->lastDay()->format('Y-m-d'));
        self::assertSame('2027-2028', $period->next()->label());
        self::assertSame('2025-2026', $period->previous()->label());
    }

    public function testTheRttRunOverTheCalendarYear(): void
    {
        $period = TimeOffPeriod::of(TimeOffType::Rtt, 2026);

        self::assertSame('2026-01-01', $period->firstDay()->format('Y-m-d'));
        self::assertSame('2026-12-31', $period->lastDay()->format('Y-m-d'));
        self::assertFalse($period->contains(new \DateTimeImmutable('2027-01-01')));
    }

    public function testATypeWithoutBalanceHasNoPeriod(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TimeOffPeriod::of(TimeOffType::Unpaid, 2026);
    }
}
