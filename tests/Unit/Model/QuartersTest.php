<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Quarters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuartersTest extends TestCase
{
    #[DataProvider('percentProvider')]
    public function testPercentOfTheEstimate(int $quarters, int $estimateDays, ?int $percent): void
    {
        self::assertSame($percent, Quarters::percentOf($quarters, $estimateDays));
    }

    /**
     * @return \Generator<string, array{int, int, int|null}>
     */
    public static function percentProvider(): \Generator
    {
        yield 'rounded' => [13, 26, 13];
        yield 'rounded to the nearest percent' => [85, 81, 26];
        yield 'a tiny gap never reads 0 %' => [1, 200, 1];
        yield 'a tiny gap below the estimate never reads 0 %' => [-1, 200, -1];
        yield 'below the estimate keeps its sign' => [-24, 20, -30];
        yield 'no gap' => [0, 20, 0];
        yield 'no share of a 0-day estimate' => [13, 0, null];
    }
}
