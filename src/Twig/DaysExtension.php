<?php

declare(strict_types=1);

namespace App\Twig;

use App\Model\Quarters;
use Twig\Attribute\AsTwigFilter;

final class DaysExtension
{
    private const array WEEKDAYS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];

    #[AsTwigFilter('days')]
    public function days(int $quarters): string
    {
        return Quarters::toDays($quarters);
    }

    #[AsTwigFilter('quarter_fraction')]
    public function quarterFraction(int $quarters): string
    {
        return Quarters::fraction($quarters);
    }

    #[AsTwigFilter('weekday')]
    public function weekday(\DateTimeImmutable $day): string
    {
        return self::WEEKDAYS[(int) $day->format('N')];
    }
}
