<?php

declare(strict_types=1);

namespace App\Enum\Type;

enum HolidayAdjustmentType: string
{
    case Added = 'added';
    case Removed = 'removed';
}
