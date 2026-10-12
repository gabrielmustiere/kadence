<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Tag;
use App\Enum\Type\TagCategory;
use App\Repository\TagRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The tag filter above the team of a leaf: one list per category, each bound to the tagFilter prop of the live lot form
 * that holds it.
 */
#[AsTwigComponent]
final class TagFilter
{
    /** @var array<string, string> the tag id chosen in each category, '' for any, by category value */
    public array $selected = [];

    public function __construct(
        private readonly TagRepository $tagRepository,
    ) {
    }

    /**
     * @return list<array{category: TagCategory, tags: list<Tag>}> the categories having tags
     */
    public function getCategories(): array
    {
        $tags = $this->tagRepository->findAllByCategory();

        return array_values(array_filter(
            array_map(static fn (TagCategory $category): array => ['category' => $category, 'tags' => $tags[$category->value]], TagCategory::cases()),
            static fn (array $item): bool => [] !== $item['tags'],
        ));
    }
}
