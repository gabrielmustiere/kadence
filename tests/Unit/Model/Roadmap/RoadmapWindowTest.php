<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Roadmap;

use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Week;
use PHPUnit\Framework\TestCase;

/**
 * Anchored on the week of Monday 2026-09-28: the window runs from Monday 2026-08-31 to Sunday 2027-06-13, 287 days.
 */
final class RoadmapWindowTest extends TestCase
{
    private const float DAY = 100 / 287;

    public function testWindowRunsFromFourWeeksBeforeToThirtySixWeeksAfterTheAnchor(): void
    {
        $window = $this->window();

        self::assertSame('2026-08-31', $window->firstDay()->format('Y-m-d'));
        self::assertSame('2027-06-13', $window->lastDay()->format('Y-m-d'));
        self::assertSame(41, RoadmapWindow::WEEK_COUNT);
    }

    public function testNavigationMovesByFourWeeks(): void
    {
        $window = $this->window();

        self::assertSame('2026-W36', $window->previous()->iso());
        self::assertSame('2026-W44', $window->next()->iso());
    }

    public function testBarWithinTheWindow(): void
    {
        $bar = $this->window()->bar(new \DateTimeImmutable('2026-09-28'), new \DateTimeImmutable('2026-10-02'));

        self::assertNotNull($bar);
        self::assertEqualsWithDelta(28 * self::DAY, $bar->left, 1e-9);
        self::assertEqualsWithDelta(5 * self::DAY, $bar->width, 1e-9);
        self::assertFalse($bar->cutStart);
        self::assertFalse($bar->cutEnd);
        self::assertSame('left: 9.7561%; width: 1.7422%', $bar->style());
    }

    public function testBarIsCutAtTheEdges(): void
    {
        $window = $this->window();

        $before = $window->bar(new \DateTimeImmutable('2026-08-24'), new \DateTimeImmutable('2026-09-06'));
        self::assertNotNull($before);
        self::assertSame(0.0, $before->left);
        self::assertEqualsWithDelta(7 * self::DAY, $before->width, 1e-9);
        self::assertTrue($before->cutStart);
        self::assertFalse($before->cutEnd);
        self::assertSame('2026-08-24', $before->from->format('Y-m-d'), 'A cut bar keeps its whole span.');
        self::assertSame('2026-09-06', $before->to->format('Y-m-d'));

        $after = $window->bar(new \DateTimeImmutable('2027-06-10'), new \DateTimeImmutable('2027-06-20'));
        self::assertNotNull($after);
        self::assertEqualsWithDelta(4 * self::DAY, $after->width, 1e-9);
        self::assertTrue($after->cutEnd);
    }

    public function testBarOutsideTheWindowIsNotShown(): void
    {
        $window = $this->window();

        self::assertNull($window->bar(new \DateTimeImmutable('2026-08-01'), new \DateTimeImmutable('2026-08-30')));
        self::assertNull($window->bar(new \DateTimeImmutable('2027-06-14'), new \DateTimeImmutable('2027-07-01')));
    }

    public function testTodayIsPlacedOnlyWithinTheWindow(): void
    {
        $inside = new Roadmap($this->window(), new \DateTimeImmutable('2026-09-30'), []);
        $outside = new Roadmap($this->window(), new \DateTimeImmutable('2027-07-01'), []);

        self::assertEqualsWithDelta(30 * self::DAY, $inside->todayPosition(), 1e-9);
        self::assertNull($outside->todayPosition());
    }

    public function testMonthsBeginWhereTheirFirstDayFallsAndACutMonthTooShortIsLeftOut(): void
    {
        $months = new Roadmap($this->window(), new \DateTimeImmutable('2026-09-30'), [])->months();

        self::assertSame('sept. 2026', $months[0][0]);
        self::assertEqualsWithDelta(self::DAY, $months[0][1], 1e-9);
        self::assertSame('oct.', $months[1][0]);
        self::assertSame('janv. 2027', $months[4][0]);
        self::assertSame('juin', $months[array_key_last($months)][0]);
    }

    private function window(): RoadmapWindow
    {
        return RoadmapWindow::around(Week::fromIso('2026-W40'));
    }
}
