<?php

declare(strict_types=1);

namespace App\Enum\Type;

enum HolidayCalendar: string
{
    case France = 'fr';
    case Belgium = 'be';

    public function label(): string
    {
        return match ($this) {
            self::France => 'France',
            self::Belgium => 'Belgique',
        };
    }
}
