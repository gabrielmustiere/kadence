<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\TimeOff;

use App\Enum\Type\TimeOffType;
use App\Model\TimeOff\Balance;
use App\Model\TimeOff\TimeOffPeriod;
use PHPUnit\Framework\TestCase;

final class BalanceTest extends TestCase
{
    public function testTheBalanceIsWhatTheRequestsLeaveOfTheEntitlements(): void
    {
        $balance = new Balance(TimeOffPeriod::of(TimeOffType::PaidLeave, 2026), 50, 30, 14);

        self::assertSame(6, $balance->remainingHalfDays());
        self::assertFalse($balance->isOverrun());
    }

    public function testARequestBeyondTheEntitlementsLeavesANegativeBalance(): void
    {
        $balance = new Balance(TimeOffPeriod::of(TimeOffType::PaidLeave, 2026), 50, 30, 14)->with(10);

        self::assertSame(-4, $balance->remainingHalfDays());
        self::assertTrue($balance->isOverrun());
    }
}
