<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\Type\HolidayCalendar;
use App\Service\LegalHolidays;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LegalHolidaysTest extends TestCase
{
    public function testFranceHasElevenLegalHolidaysWeekendsIncluded(): void
    {
        self::assertSame([
            '2026-01-01' => 'Jour de l\'an',
            '2026-04-06' => 'Lundi de Pâques',
            '2026-05-01' => 'Fête du Travail',
            '2026-05-08' => 'Victoire 1945',
            '2026-05-14' => 'Ascension',
            '2026-05-25' => 'Lundi de Pentecôte',
            '2026-07-14' => 'Fête nationale',
            '2026-08-15' => 'Assomption',
            '2026-11-01' => 'Toussaint',
            '2026-11-11' => 'Armistice 1918',
            '2026-12-25' => 'Noël',
        ], new LegalHolidays()->forYear(HolidayCalendar::France, 2026));
    }

    public function testBelgiumHasTenLegalHolidaysWithItsOwnNationalDay(): void
    {
        self::assertSame([
            '2026-01-01' => 'Jour de l\'an',
            '2026-04-06' => 'Lundi de Pâques',
            '2026-05-01' => 'Fête du Travail',
            '2026-05-14' => 'Ascension',
            '2026-05-25' => 'Lundi de Pentecôte',
            '2026-07-21' => 'Fête nationale',
            '2026-08-15' => 'Assomption',
            '2026-11-01' => 'Toussaint',
            '2026-11-11' => 'Armistice 1918',
            '2026-12-25' => 'Noël',
        ], new LegalHolidays()->forYear(HolidayCalendar::Belgium, 2026));
    }

    #[DataProvider('movableFeastProvider')]
    public function testMovableFeastsFollowEasterOfTheYear(HolidayCalendar $calendar, int $year, string $easterMonday, string $ascension, string $pentecostMonday): void
    {
        $holidays = new LegalHolidays()->forYear($calendar, $year);

        self::assertSame('Lundi de Pâques', $holidays[$easterMonday] ?? null);
        self::assertSame('Ascension', $holidays[$ascension] ?? null);
        self::assertSame('Lundi de Pentecôte', $holidays[$pentecostMonday] ?? null);
    }

    /**
     * @return \Generator<array{HolidayCalendar, int, string, string, string}>
     */
    public static function movableFeastProvider(): \Generator
    {
        foreach (HolidayCalendar::cases() as $calendar) {
            yield [$calendar, 2027, '2027-03-29', '2027-05-06', '2027-05-17'];
            yield [$calendar, 2028, '2028-04-17', '2028-05-25', '2028-06-05'];
        }
    }
}
