<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\TagCategory;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesTags
{
    abstract private function entityManager(): EntityManagerInterface;

    /** @param non-empty-string|null $label */
    private function createTag(TagCategory $category = TagCategory::TechnicalSkill, ?string $label = null): Tag
    {
        $tag = new Tag($category, $label ?? uniqid('Tag ', true));

        $entityManager = $this->entityManager();
        $entityManager->persist($tag);
        $entityManager->flush();

        return $tag;
    }

    private function giveTags(User $user, Tag ...$tags): User
    {
        $user->replaceTags([...$user->tagsOf(TagCategory::TechnicalSkill), ...$user->tagsOf(TagCategory::FunctionalExperience), ...$user->tagsOf(TagCategory::TeamType), ...$tags]);
        $this->entityManager()->flush();

        return $user;
    }
}
