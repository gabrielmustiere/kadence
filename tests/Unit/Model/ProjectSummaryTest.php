<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Entity\Lot;
use App\Entity\Project;
use App\Model\LotSummary;
use App\Model\ProjectSummary;
use PHPUnit\Framework\TestCase;

final class ProjectSummaryTest extends TestCase
{
    public function testTheProjectedCostAddsWhatIsLeftToWhatIsEnteredWithoutOffsettingTheOverrun(): void
    {
        $summary = self::project([], estimateDays: 390, entered: 1450, remaining: 399, overrun: 104);

        self::assertSame(1849, $summary->projectedQuarters());
        self::assertSame(289, $summary->projectedGapQuarters());
    }

    public function testTheProjectedGapIsNegativeUnderTheEstimateAndNullOnIt(): void
    {
        self::assertSame(-8, self::project([], estimateDays: 10, entered: 20, remaining: 12)->projectedGapQuarters());
        self::assertSame(0, self::project([], estimateDays: 10, entered: 28, remaining: 12)->projectedGapQuarters());
    }

    public function testTheProjectedCostIsAMinimumWithALeafOverrunWithoutProgress(): void
    {
        $summary = self::project([LotSummaryTest::leaf(entered: 4 * 4, remaining: 6 * 4), LotSummaryTest::leaf(entered: 12 * 4, overrun: 2 * 4)]);

        self::assertTrue($summary->isProjectedAtLeast());
    }

    public function testTheProjectedCostIsAMinimumWithASubLotOverrunWithoutProgress(): void
    {
        $subLot = LotSummaryTest::leaf(entered: 12 * 4, overrun: 2 * 4);
        $lot = new LotSummary(new Lot(new Project()), [$subLot], 10, 0, 0, 0, true, 12 * 4);

        self::assertTrue(self::project([$lot])->isProjectedAtLeast());
    }

    public function testTheProjectedCostIsExactWhenEveryRemainingIsKnown(): void
    {
        $summary = self::project([LotSummaryTest::leaf(entered: 4 * 4, remaining: 6 * 4), LotSummaryTest::leaf(entered: 12 * 4, remaining: 3 * 4, overrun: 2 * 4, percent: 80)]);

        self::assertFalse($summary->isProjectedAtLeast());
    }

    /**
     * @param list<LotSummary> $lots
     */
    private static function project(array $lots, int $estimateDays = 20, int $entered = 0, int $remaining = 0, int $overrun = 0): ProjectSummary
    {
        return new ProjectSummary(new Project(), $lots, $estimateDays, 0, 0, 0, 0, $entered > 0, $entered, $remaining, $overrun);
    }
}
