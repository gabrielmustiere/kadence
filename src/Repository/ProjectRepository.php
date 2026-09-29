<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

use function Symfony\Component\String\u;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Projects sorted by title, accents folded so that « Évolution » sorts with E.
     *
     * @return list<Project>
     */
    public function findAllForList(?User $owner = null): array
    {
        $queryBuilder = $this->createTreeQueryBuilder();

        if (null !== $owner) {
            $queryBuilder
                ->andWhere('EXISTS (SELECT owned.id FROM App\Entity\Lot owned WHERE owned.project = p AND owned.owner = :owner)')
                ->setParameter('owner', $owner);
        }

        /** @var list<Project> $projects */
        $projects = $queryBuilder->getQuery()->getResult();
        usort($projects, static fn (Project $a, Project $b): int => self::sortKey($a) <=> self::sortKey($b));

        return $projects;
    }

    public function findOneForDetail(int $id): ?Project
    {
        /** @var Project|null $project */
        $project = $this->createTreeQueryBuilder()
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        return $project;
    }

    /**
     * @return list<string>
     */
    public function findTitlesExcept(?int $excludedId): array
    {
        $queryBuilder = $this->createQueryBuilder('p')->select('p.title');

        if (null !== $excludedId) {
            $queryBuilder->andWhere('p.id <> :excluded')->setParameter('excluded', $excludedId);
        }

        /** @var list<string> $titles */
        $titles = $queryBuilder->getQuery()->getSingleColumnResult();

        return $titles;
    }

    private function createTreeQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->addSelect('l', 'c', 'o')
            ->leftJoin('p.lots', 'l')
            ->leftJoin('l.children', 'c')
            ->leftJoin('l.owner', 'o');
    }

    private static function sortKey(Project $project): string
    {
        return u($project->getTitle() ?? '')->ascii()->lower()->toString();
    }
}
