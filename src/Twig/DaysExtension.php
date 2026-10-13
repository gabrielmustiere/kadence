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

    /**
     * A gap to the estimate: days then share of the estimate, signed on both with a typographic minus when asked.
     */
    #[AsTwigFilter('gap')]
    public function gap(int $quarters, int $estimateDays, bool $signed = false): string
    {
        $sign = match (true) {
            !$signed => '',
            $quarters < 0 => "\u{2212}",
            default => '+',
        };
        $gap = $sign . Quarters::toDays(abs($quarters));
        $percent = Quarters::percentOf($quarters, $estimateDays);

        return null === $percent ? $gap : \sprintf('%s (%s%d %%)', $gap, $sign, abs($percent));
    }

    #[AsTwigFilter('half_days')]
    public function halfDays(int $halfDays): string
    {
        return Quarters::toDays($halfDays * Quarters::PER_DAY / 2);
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
