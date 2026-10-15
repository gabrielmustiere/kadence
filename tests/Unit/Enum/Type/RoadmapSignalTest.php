<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum\Type;

use App\Enum\Type\RoadmapSignal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoadmapSignalTest extends TestCase
{
    #[DataProvider('signalProvider')]
    public function testEverySignalSpeaksOfLots(RoadmapSignal $signal): void
    {
        foreach ([$signal->label(), $signal->meaning(), $signal->reason()] as $text) {
            self::assertDoesNotMatchRegularExpression('/\bfeuilles?\b/iu', $text);
            self::assertStringNotContainsStringIgnoringCase('partie future', $text);
        }
    }

    public function testAProgressToRefreshNoLongerWithholdsTheEnd(): void
    {
        $withholding = array_values(array_filter(RoadmapSignal::cases(), static fn (RoadmapSignal $signal): bool => $signal->withholdsEnd()));

        self::assertSame([RoadmapSignal::ToEstimate, RoadmapSignal::WithoutStart, RoadmapSignal::WithoutTeam, RoadmapSignal::Overrun, RoadmapSignal::TeamToReview], $withholding);
    }

    public function testPartialPlanningIsNotAPartialEstimate(): void
    {
        self::assertSame('planification partielle', RoadmapSignal::PartialPlanning->label());
    }

    /**
     * @return \Generator<string, array{RoadmapSignal}>
     */
    public static function signalProvider(): \Generator
    {
        foreach (RoadmapSignal::cases() as $signal) {
            yield $signal->value => [$signal];
        }
    }
}
