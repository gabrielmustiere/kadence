<?php

declare(strict_types=1);

namespace App\Model\Timesheet;

use App\Entity\Lot;

use function Symfony\Component\String\u;

/**
 * Orders leaves as the project tree shows them: projects by title (accents folded), then lots and sub-lots by creation.
 */
final class LeafOrder
{
    public static function compare(Lot $a, Lot $b): int
    {
        return self::key($a) <=> self::key($b);
    }

    /**
     * @return array{string, int, int, int}
     */
    private static function key(Lot $lot): array
    {
        $parent = $lot->getParent();

        return [
            u($lot->getProject()->getTitle() ?? '')->ascii()->lower()->toString(),
            $lot->getProject()->getId() ?? 0,
            ($parent ?? $lot)->getId() ?? 0,
            null === $parent ? 0 : $lot->getId() ?? 0,
        ];
    }
}
