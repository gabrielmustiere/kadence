<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Entity\Project;
use App\Entity\User;
use App\Model\LotSummary;
use PHPUnit\Framework\TestCase;

final class LotSummaryTest extends TestCase
{
    public function testALeafWithinItsEstimateHasAKnownRemaining(): void
    {
        self::assertFalse(self::leaf(entered: 6 * 4, remaining: 4 * 4)->isRemainingUnknown());
    }

    public function testALeafOverrunWithoutProgressHasAnUnknownRemaining(): void
    {
        self::assertTrue(self::leaf(entered: 12 * 4, overrun: 2 * 4)->isRemainingUnknown());
    }

    public function testALeafOverrunWithAProgressInForceHasAKnownRemaining(): void
    {
        self::assertFalse(self::leaf(entered: 12 * 4, remaining: 3 * 4, overrun: 2 * 4, percent: 80)->isRemainingUnknown());
    }

    public function testALeafDeclaredCompleteHasNothingLeft(): void
    {
        self::assertFalse(self::leaf(entered: 9 * 4, percent: 100)->isRemainingUnknown());
    }

    public function testASplitLotLeavesItToItsSubLots(): void
    {
        $subLot = self::leaf(entered: 12 * 4, overrun: 2 * 4);
        $lot = new LotSummary(new Lot(new Project()), [$subLot], 10, 0, 0, 0, true, 12 * 4, 0, 2 * 4);

        self::assertFalse($lot->isRemainingUnknown());
    }

    /**
     * @param int<0, max>      $entered
     * @param int<0, max>      $remaining
     * @param int<1, 100>|null $percent
     */
    public static function leaf(int $entered, int $remaining = 0, int $overrun = 0, ?int $percent = null): LotSummary
    {
        $lot = new Lot(new Project());
        $progress = null === $percent ? null : new LotProgress($lot, new User(), new \DateTimeImmutable('2026-10-01'), $percent);

        return new LotSummary(
            $lot,
            [],
            10,
            0,
            0,
            0,
            $entered > 0,
            $entered,
            $remaining,
            $overrun,
            progress: $progress,
            projectedQuarters: null === $progress ? null : $entered + $remaining,
        );
    }
}
