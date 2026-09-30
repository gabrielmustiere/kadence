<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\Type\HolidayCalendar;

final class LegalHolidays
{
    /**
     * @return array<string, non-empty-string> labels by day (Y-m-d) in date order, weekends included
     */
    public function forYear(HolidayCalendar $calendar, int $year): array
    {
        $easter = new \DateTimeImmutable(\sprintf('%d-03-21', $year))->modify(\sprintf('+%d days', easter_days($year)));
        $holidays = [
            \sprintf('%d-01-01', $year) => 'Jour de l\'an',
            $easter->modify('+1 day')->format('Y-m-d') => 'Lundi de Pâques',
            \sprintf('%d-05-01', $year) => 'Fête du Travail',
            $easter->modify('+39 days')->format('Y-m-d') => 'Ascension',
            $easter->modify('+50 days')->format('Y-m-d') => 'Lundi de Pentecôte',
            \sprintf('%d-08-15', $year) => 'Assomption',
            \sprintf('%d-11-01', $year) => 'Toussaint',
            \sprintf('%d-11-11', $year) => 'Armistice 1918',
            \sprintf('%d-12-25', $year) => 'Noël',
        ];
        $holidays += match ($calendar) {
            HolidayCalendar::France => [
                \sprintf('%d-05-08', $year) => 'Victoire 1945',
                \sprintf('%d-07-14', $year) => 'Fête nationale',
            ],
            HolidayCalendar::Belgium => [
                \sprintf('%d-07-21', $year) => 'Fête nationale',
            ],
        };
        ksort($holidays);

        return $holidays;
    }
}
