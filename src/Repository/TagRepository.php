<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\TagCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * @return array<value-of<TagCategory>, list<Tag>> every tag, in alphabetical order, by category value in the
     *                                                 order of the categories
     */
    public function findAllByCategory(): array
    {
        /** @var list<Tag> $tags */
        $tags = $this->findAll();
        usort($tags, Tag::compare(...));

        $byCategory = [];
        foreach (TagCategory::cases() as $category) {
            $byCategory[$category->value] = array_values(array_filter($tags, static fn (Tag $tag): bool => $tag->getCategory() === $category));
        }

        return $byCategory;
    }

    /**
     * @return list<Tag> in alphabetical order
     */
    public function findByCategory(TagCategory $category): array
    {
        /** @var list<Tag> $tags */
        $tags = $this->findBy(['category' => $category]);
        usort($tags, Tag::compare(...));

        return $tags;
    }

    /**
     * @return list<string>
     */
    public function findLabelsExcept(TagCategory $category, ?int $excludedId): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('t.label')
            ->andWhere('t.category = :category')
            ->setParameter('category', $category);

        if (null !== $excludedId) {
            $queryBuilder->andWhere('t.id <> :excluded')->setParameter('excluded', $excludedId);
        }

        /** @var list<string> $labels */
        $labels = $queryBuilder->getQuery()->getSingleColumnResult();

        return $labels;
    }

    /**
     * @return array<int, int> the number of people, active or not, carrying each tag, by tag id; tags nobody carries
     *                         are left out
     */
    public function countHoldersByTag(): array
    {
        /** @var list<array{tagId: int|string, holders: int|string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('t.id AS tagId', 'COUNT(u.id) AS holders')
            ->from(User::class, 'u')
            ->join('u.tags', 't')
            ->groupBy('t.id')
            ->getQuery()
            ->getArrayResult();

        $holders = [];
        foreach ($rows as $row) {
            $holders[(int) $row['tagId']] = (int) $row['holders'];
        }

        return $holders;
    }
}
