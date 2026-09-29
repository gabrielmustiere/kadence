<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Week;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeekTest extends TestCase
{
    public function testContainingStartsOnTheMondayOfTheDayWeek(): void
    {
        self::assertSame('2026-09-28 00:00', Week::containing(new \DateTimeImmutable('2026-10-04 18:30'))->monday->format('Y-m-d H:i'));
        self::assertSame('2026-09-28', Week::containing(new \DateTimeImmutable('2026-09-28 08:00'))->monday->format('Y-m-d'));
    }

    public function testIsoRoundTrip(): void
    {
        $week = Week::fromIso('2026-W40');

        self::assertSame('2026-09-28', $week->monday->format('Y-m-d'));
        self::assertSame('2026-W40', $week->iso());
    }

    public function testNeighboursCrossYears(): void
    {
        $week = Week::fromIso('2026-W53');

        self::assertSame('2026-12-28', $week->monday->format('Y-m-d'));
        self::assertSame('2027-W01', $week->next()->iso());
        self::assertSame('2026-W52', $week->previous()->iso());
    }

    public function testDaysAreMondayToFriday(): void
    {
        $days = array_map(static fn (\DateTimeImmutable $day): string => $day->format('D d'), Week::fromIso('2026-W40')->days());

        self::assertSame(['Mon 28', 'Tue 29', 'Wed 30', 'Thu 01', 'Fri 02'], $days);
    }

    public function testContainsOnlyWorkingDays(): void
    {
        $week = Week::fromIso('2026-W40');

        self::assertTrue($week->contains(new \DateTimeImmutable('2026-09-28')));
        self::assertTrue($week->contains(new \DateTimeImmutable('2026-10-02 23:00')));
        self::assertFalse($week->contains(new \DateTimeImmutable('2026-10-03')));
        self::assertFalse($week->contains(new \DateTimeImmutable('2026-09-27')));
    }

    #[DataProvider('invalidIsoProvider')]
    public function testFromIsoRefusesInvalidWeeks(string $iso): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Week::fromIso($iso);
    }

    /**
     * @return \Generator<array{string}>
     */
    public static function invalidIsoProvider(): \Generator
    {
        yield 'no week' => ['2026-40'];
        yield 'week zero' => ['2026-W00'];
        yield 'week 53 of a 52-week year' => ['2027-W53'];
        yield 'garbage' => ['semaine'];
    }
}
