<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Entity\Lot;

final class LeafPath
{
    /**
     * « Lot · Sous-lot » for a sub-lot, the title of the lot otherwise.
     */
    public static function of(Lot $leaf): string
    {
        $parent = $leaf->getParent();

        return (null === $parent ? '' : $parent->getTitle() . ' · ') . $leaf->getTitle();
    }
}
