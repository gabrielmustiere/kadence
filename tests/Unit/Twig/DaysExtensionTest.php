<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\DaysExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DaysExtensionTest extends TestCase
{
    #[DataProvider('gapProvider')]
    public function testGapReadsDaysThenShareOfTheEstimate(int $quarters, int $estimateDays, bool $signed, string $gap): void
    {
        self::assertSame($gap, new DaysExtension()->gap($quarters, $estimateDays, $signed));
    }

    /**
     * @return \Generator<string, array{int, int, bool, string}>
     */
    public static function gapProvider(): \Generator
    {
        yield 'overrun' => [13, 26, false, '3,25 j (13 %)'];
        yield 'projected above the estimate' => [26, 26, true, '+6,5 j (+25 %)'];
        yield 'projected below the estimate' => [-24, 20, true, "\u{2212}6 j (\u{2212}30 %)"];
        yield 'no share of a 0-day estimate' => [13, 0, false, '3,25 j'];
    }
}
