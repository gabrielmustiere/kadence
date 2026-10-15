<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Roadmap;

use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\RoadmapView;
use App\Model\Week;
use PHPUnit\Framework\TestCase;

final class RoadmapViewTest extends TestCase
{
    public function testKeepsASignalOfTheBoardForAPlanner(): void
    {
        $view = RoadmapView::tryFrom('2026-W42', 'to_estimate', true);

        self::assertNotNull($view);
        self::assertSame(RoadmapSignal::ToEstimate, $view->signal);
        self::assertSame(['week' => '2026-W42', 'signal' => 'to_estimate'], $view->routeParams());
        self::assertSame(['signal' => 'to_estimate'], $view->signalParams());
    }

    public function testLeavesOutTheSignalOfSomeoneWhoDoesNotPlan(): void
    {
        $view = RoadmapView::of(Week::fromIso('2026-W42'), 'to_estimate', false);

        self::assertNull($view->signal);
        self::assertSame(['week' => '2026-W42'], $view->routeParams());
        self::assertSame([], $view->signalParams());
    }

    public function testLeavesOutAnUnknownSignalOrOneWithoutACounter(): void
    {
        foreach (['nope', 'completed', 'unsplit', 'partial_planning', '', null] as $signal) {
            self::assertNull(RoadmapView::of(Week::fromIso('2026-W42'), $signal, true)->signal, (string) $signal);
        }
    }

    public function testHasNoViewWithoutAnExistingWeek(): void
    {
        self::assertNull(RoadmapView::tryFrom(null, 'to_estimate', true));
        self::assertNull(RoadmapView::tryFrom('2026-W99', 'to_estimate', true));
        self::assertNull(RoadmapView::tryFrom('demain', null, true));
    }
}
