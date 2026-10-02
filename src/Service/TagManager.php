<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tag;
use App\Enum\Type\TagCategory;
use App\Model\TitleComparison;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TagManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TagRepository $tagRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function create(TagCategory $category, string $label): Tag
    {
        $tag = new Tag($category, self::label($label));
        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $tag;
    }

    public function rename(Tag $tag, string $label): void
    {
        $tag->rename(self::label($label));
        $this->entityManager->flush();
    }

    /**
     * Without foreign keys enforced by the database, nothing would clear the tag from the people carrying it.
     */
    public function delete(Tag $tag): void
    {
        foreach ($this->userRepository->findHoldersOf($tag) as $holder) {
            $holder->removeTag($tag);
        }

        $this->entityManager->remove($tag);
        $this->entityManager->flush();
    }

    /**
     * The tags of the category bearing these labels, case and surrounding spaces ignored: the missing ones are created,
     * persisted but not flushed, so that they only exist once what they were typed for is saved.
     *
     * @param list<string> $labels
     *
     * @return list<Tag>
     */
    public function resolve(TagCategory $category, array $labels): array
    {
        $existing = [];
        foreach ($this->tagRepository->findByCategory($category) as $tag) {
            $existing[TitleComparison::normalize($tag->getLabel())] = $tag;
        }

        $tags = [];
        foreach ($labels as $label) {
            $label = trim($label);
            if ('' === $label) {
                continue;
            }

            $key = TitleComparison::normalize($label);
            if (!isset($existing[$key])) {
                $existing[$key] = new Tag($category, $label);
                $this->entityManager->persist($existing[$key]);
            }
            $tags[$key] = $existing[$key];
        }

        return array_values($tags);
    }

    /**
     * @return non-empty-string
     */
    private static function label(string $label): string
    {
        $label = trim($label);
        if ('' === $label) {
            throw new \LogicException('A validated tag has a label.');
        }

        return $label;
    }
}
