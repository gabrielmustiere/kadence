<?php

declare(strict_types=1);

namespace App\Enum\Type;

enum HalfDay: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'matin',
            self::Afternoon => 'après-midi',
        };
    }
}
