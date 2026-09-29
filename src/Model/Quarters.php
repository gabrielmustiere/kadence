<?php

declare(strict_types=1);

namespace App\Model;

final class Quarters
{
    public const int PER_DAY = 4;

    private const array FRACTIONS = [1 => '¼', 2 => '½', 3 => '¾', 4 => '1'];

    public static function toDays(int $quarters): string
    {
        return rtrim(rtrim(number_format($quarters / self::PER_DAY, 2, ',', ''), '0'), ',') . ' j';
    }

    public static function daysRoundedUp(int $quarters): int
    {
        return intdiv($quarters + self::PER_DAY - 1, self::PER_DAY);
    }

    public static function fraction(int $quarters): string
    {
        return self::FRACTIONS[$quarters] ?? '0';
    }
}
