<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Type\TagCategory;
use App\Repository\TagRepository;
use Doctrine\ORM\Mapping as ORM;

use function Symfony\Component\String\u;

/**
 * A word of the shared vocabulary describing people: a technical skill, a functional experience or a team type. Its
 * category is set once and for all; its label can be renamed.
 */
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_tag_category_label', columns: ['category', 'label'])]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    #[ORM\Column(name: 'category', length: 20, enumType: TagCategory::class)]
    private TagCategory $category;

    /** @var non-empty-string */
    #[ORM\Column(name: 'label', length: 60)]
    private string $label;

    /** @param non-empty-string $label */
    public function __construct(TagCategory $category, string $label)
    {
        $this->category = $category;
        $this->label = $label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): TagCategory
    {
        return $this->category;
    }

    /** @return non-empty-string */
    public function getLabel(): string
    {
        return $this->label;
    }

    /** @param non-empty-string $label */
    public function rename(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Alphabetical order, accents folded so that « Écriture » sorts with E.
     */
    public static function compare(self $a, self $b): int
    {
        return u($a->label)->ascii()->lower()->toString() <=> u($b->label)->ascii()->lower()->toString();
    }
}
