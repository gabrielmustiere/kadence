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
    public function testRemainingIsExtrapolatedFromTheTimeEnteredSoFar(): void
    {
        self::assertSame(15 * 4, $this->progress(40)->remaining(10 * 4, 20 * 4));
        self::assertSame(51 * 4, $this->progress(15)->remaining(9 * 4, 18 * 4));
    }

    public function testTimeEnteredAfterTheDeclarationRaisesTheRemaining(): void
    {
        $progress = $this->progress(40);

        self::assertSame(15 * 4, $progress->remaining(10 * 4, 20 * 4));
        self::assertSame(78, $progress->remaining(13 * 4, 20 * 4));
    }

    public function testRemainingComesFromTheEstimateWhenNoTimeIsEntered(): void
    {
        self::assertSame(7 * 4, $this->progress(30)->remaining(0, 10 * 4));
    }

    public function testAnOverrunLeafStillGetsARemaining(): void
    {
        self::assertSame(3 * 4, $this->progress(80)->remaining(12 * 4, 10 * 4));
    }

    public function testNothingIsLeftAtAHundredPercent(): void
    {
        self::assertSame(0, $this->progress(100)->remaining(10 * 4, 20 * 4));
        self::assertSame(0, $this->progress(100)->remaining(0, 20 * 4));
        self::assertTrue($this->progress(100)->isComplete());
    }

    public function testRemainingIsRoundedUpToTheQuarter(): void
    {
        self::assertSame(5, $this->progress(40)->remaining(3, 20 * 4));
        self::assertSame(1, $this->progress(95)->remaining(0, 1 * 4));
    }

    public function testAProgressDeclaredMoreThanAWeekAgoIsToRefreshOnceTimeIsEnteredSince(): void
    {
        $progress = $this->progress(40);
        $today = new \DateTimeImmutable('2026-10-12');

        self::assertTrue($progress->isToRefresh(new \DateTimeImmutable('2026-10-05'), $today));
        self::assertFalse($progress->isToRefresh(new \DateTimeImmutable('2026-10-02'), $today), 'Nothing entered since the declaration.');
        self::assertFalse($progress->isToRefresh(null, $today));
        self::assertFalse($progress->isToRefresh(new \DateTimeImmutable('2026-10-08'), new \DateTimeImmutable('2026-10-09')), 'Declared a week ago exactly.');
    }

    public function testALeafDeclaredCompleteIsNeverToRefresh(): void
    {
        self::assertFalse($this->progress(100)->isToRefresh(new \DateTimeImmutable('2026-10-05'), new \DateTimeImmutable('2026-10-12')));
    }

    public function testADeclarationAtZeroPercentWithdrawsTheProgress(): void
    {
        self::assertNull(LeafProgress::fromDeclaration($this->declaration(0)));
    }

    public function testADeclarationGivesTheProgressInForce(): void
    {
        $progress = LeafProgress::fromDeclaration($this->declaration(40));

        self::assertNotNull($progress);
        self::assertSame(40, $progress->percent);
        self::assertSame('2026-10-02', $progress->declaredOn->format('Y-m-d'));
        self::assertFalse($progress->isComplete());
    }

    /**
     * @param int<1, 100> $percent
     */
    private function progress(int $percent): LeafProgress
    {
        return new LeafProgress($percent, new \DateTimeImmutable('2026-10-02'));
    }

    /**
     * @param int<0, 100> $percent
     */
    private function declaration(int $percent): LotProgress
    {
        return new LotProgress(new Lot(new Project()), new User(), new \DateTimeImmutable('2026-10-02'), $percent);
    }
}
