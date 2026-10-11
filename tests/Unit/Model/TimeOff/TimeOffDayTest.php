<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\TimeOff;

use App\Enum\Type\HalfDay;
use App\Enum\Type\TimeOffStatus;
use App\Enum\Type\TimeOffType;
use App\Model\TimeOff\TimeOffDay;
use PHPUnit\Framework\TestCase;

final class TimeOffDayTest extends TestCase
{
    public function testAWholePendingDayIsLockedAndNamed(): void
    {
        $day = new TimeOffDay([
            [HalfDay::Morning, TimeOffType::PaidLeave, TimeOffStatus::Pending],
            [HalfDay::Afternoon, TimeOffType::PaidLeave, TimeOffStatus::Pending],
        ]);

        self::assertSame(0, $day->capQuarters());
        self::assertSame('Congé · CP · en attente', $day->label());
    }

    public function testAHalfDayLeavesHalfADayToEnter(): void
    {
        $day = new TimeOffDay([[HalfDay::Afternoon, TimeOffType::Rtt, TimeOffStatus::Approved]]);

        self::assertSame(2, $day->capQuarters());
        self::assertSame('Congé · RTT (après-midi)', $day->label());
    }

    public function testTwoDifferentHalvesAreNamedEach(): void
    {
        $day = new TimeOffDay([
            [HalfDay::Morning, TimeOffType::PaidLeave, TimeOffStatus::Approved],
            [HalfDay::Afternoon, TimeOffType::Rtt, TimeOffStatus::Pending],
        ]);

        self::assertSame(0, $day->capQuarters());
        self::assertSame('Congé · CP (matin) · RTT (après-midi, en attente)', $day->label());
    }
}
