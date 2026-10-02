<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Schedule;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Entity\Project;
use App\Entity\User;
use App\Model\Schedule\LeafProgress;
use PHPUnit\Framework\TestCase;

final class LeafProgressTest extends TestCase
{
    public function testRemainingIsExtrapolatedFromThePaceObserved(): void
    {
        self::assertSame(15 * 4, LeafProgress::anchoredRemaining(40, 10 * 4, 20 * 4));
    }

    public function testRemainingComesFromTheEstimateWhenNoTimeIsEntered(): void
    {
        self::assertSame(7 * 4, LeafProgress::anchoredRemaining(30, 0, 10 * 4));
    }

    public function testAnOverrunLeafStillGetsARemaining(): void
    {
        self::assertSame(3 * 4, LeafProgress::anchoredRemaining(80, 12 * 4, 10 * 4));
    }

    public function testNothingIsLeftAtAHundredPercent(): void
    {
        self::assertSame(0, LeafProgress::anchoredRemaining(100, 10 * 4, 20 * 4));
        self::assertSame(0, LeafProgress::anchoredRemaining(100, 0, 20 * 4));
    }

    public function testRemainingIsRoundedUpToTheQuarter(): void
    {
        self::assertSame(5, LeafProgress::anchoredRemaining(40, 3, 20 * 4));
        self::assertSame(1, LeafProgress::anchoredRemaining(95, 0, 1 * 4));
    }

    public function testTimeEnteredSinceTheDeclarationUsesUpTheRemaining(): void
    {
        $progress = new LeafProgress(40, new \DateTimeImmutable('2026-10-02'), 10 * 4, 15 * 4);

        self::assertSame(15 * 4, $progress->remainingAfter(10 * 4));
        self::assertSame(12 * 4, $progress->remainingAfter(13 * 4));
        self::assertSame(-4, $progress->remainingAfter(26 * 4));
    }

    public function testTimeRemovedSinceTheDeclarationGivesItBack(): void
    {
        $progress = new LeafProgress(40, new \DateTimeImmutable('2026-10-02'), 10 * 4, 15 * 4);

        self::assertSame(16 * 4, $progress->remainingAfter(9 * 4));
    }

    public function testNothingComesBackToALeafDeclaredComplete(): void
    {
        $progress = new LeafProgress(100, new \DateTimeImmutable('2026-10-02'), 10 * 4, 0);

        self::assertSame(0, $progress->remainingAfter(9 * 4));
        self::assertSame(-4, $progress->remainingAfter(11 * 4));
    }

    public function testADeclarationAtZeroPercentWithdrawsTheProgress(): void
    {
        self::assertNull(LeafProgress::fromDeclaration($this->declaration(0, 40, null)));
    }

    public function testADeclarationGivesTheProgressInForce(): void
    {
        $progress = LeafProgress::fromDeclaration($this->declaration(40, 40, 60));

        self::assertNotNull($progress);
        self::assertSame(40, $progress->percent);
        self::assertSame('2026-10-02', $progress->declaredOn->format('Y-m-d'));
        self::assertSame(40, $progress->enteredQuarters);
        self::assertSame(60, $progress->remainingQuarters);
        self::assertFalse($progress->isComplete());
    }

    /**
     * @param int<0, 100>      $percent
     * @param int<0, max>      $entered
     * @param int<0, max>|null $remaining
     */
    private function declaration(int $percent, int $entered, ?int $remaining): LotProgress
    {
        return new LotProgress(new Lot(new Project()), new User(), new \DateTimeImmutable('2026-10-02'), $percent, $entered, $remaining);
    }
}
