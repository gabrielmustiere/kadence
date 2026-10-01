<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Tag;
use App\Enum\Type\TagCategory;
use App\Validator\UniqueTagLabel;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueTagLabel]
final class TagInput
{
    public ?int $id = null;

    #[Assert\NotNull]
    public ?TagCategory $category = TagCategory::TechnicalSkill;

    #[Assert\NotBlank(message: 'Donnez un libellé au tag, par exemple « Symfony ».', normalizer: 'trim')]
    #[Assert\Length(max: 60, normalizer: 'trim')]
    public ?string $label = null;

    public static function fromTag(Tag $tag): self
    {
        $input = new self();
        $input->id = $tag->getId();
        $input->category = $tag->getCategory();
        $input->label = $tag->getLabel();

        return $input;
    }
}
