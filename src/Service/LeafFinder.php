<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Model\LeafOrder;
use App\Repository\LotRepository;

use function Symfony\Component\String\u;

final readonly class LeafFinder
{
    private const int MIN_LENGTH = 2;
    public const int LIMIT = 10;

    public function __construct(
        private LotRepository $lotRepository,
    ) {
    }

    /**
     * Leaves whose project, lot and sub-lot titles contain every word of the query, accents and case folded.
     *
     * @param list<int> $excludedIds
     *
     * @return list<Lot>
     */
    public function search(string $query, array $excludedIds = []): array
    {
        if (!$this->isSearchable($query)) {
            return [];
        }

        $terms = self::terms($query);
        $matches = array_values(array_filter(
            $this->lotRepository->findLeavesWithAncestors(),
            static fn (Lot $lot): bool => !\in_array($lot->getId(), $excludedIds, true) && self::matchesAll($lot, $terms),
        ));
        usort($matches, LeafOrder::compare(...));

        return \array_slice($matches, 0, self::LIMIT);
    }

    public function isSearchable(string $query): bool
    {
        return mb_strlen(implode('', self::terms($query))) >= self::MIN_LENGTH;
    }

    /**
     * @return list<string>
     */
    private static function terms(string $query): array
    {
        return array_values(array_filter(explode(' ', self::fold($query)), static fn (string $term): bool => '' !== $term));
    }

    /**
     * @param list<string> $terms
     */
    private static function matchesAll(Lot $lot, array $terms): bool
    {
        $haystack = self::fold(implode(' ', [$lot->getProject()->getTitle(), $lot->getParent()?->getTitle(), $lot->getTitle()]));

        foreach ($terms as $term) {
            if (!str_contains($haystack, $term)) {
                return false;
            }
        }

        return true;
    }

    private static function fold(string $value): string
    {
        return u($value)->ascii()->lower()->collapseWhitespace()->trim()->toString();
    }
}
