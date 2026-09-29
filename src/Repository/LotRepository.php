<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lot;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lot>
 */
class LotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lot::class);
    }

    /**
     * Titles of the lots sharing the same project and parent (top-level lots when $parent is null).
     *
     * @return list<string>
     */
    public function findSiblingTitles(Project $project, ?Lot $parent, ?int $excludedId): array
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->select('l.title')
            ->andWhere('l.project = :project')
            ->setParameter('project', $project);

        if (null === $parent) {
            $queryBuilder->andWhere('l.parent IS NULL');
        } else {
            $queryBuilder->andWhere('l.parent = :parent')->setParameter('parent', $parent);
        }

        if (null !== $excludedId) {
            $queryBuilder->andWhere('l.id <> :excluded')->setParameter('excluded', $excludedId);
        }

        /** @var list<string> $titles */
        $titles = $queryBuilder->getQuery()->getSingleColumnResult();

        return $titles;
    }
}
