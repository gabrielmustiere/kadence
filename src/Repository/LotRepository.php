<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lot;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * Every leaf (lot without sub-lot, or sub-lot) with its parent lot and its project.
     *
     * @return list<Lot>
     */
    public function findLeavesWithAncestors(): array
    {
        /** @var list<Lot> $leaves */
        $leaves = $this->createLeavesQueryBuilder()->getQuery()->getResult();

        return $leaves;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Lot>
     */
    public function findLeavesByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Lot> $leaves */
        $leaves = $this->createLeavesQueryBuilder()
            ->andWhere('l.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        return $leaves;
    }

    private function createLeavesQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('l')
            ->addSelect('parent', 'p')
            ->join('l.project', 'p')
            ->leftJoin('l.parent', 'parent')
            ->andWhere('l.children IS EMPTY');
    }
}
