<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeEntry>
 */
class TimeEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeEntry::class);
    }

    /**
     * The user's entries between two days included, with their leaf, its parent lot and its project.
     *
     * @return list<TimeEntry>
     */
    public function findForUserBetween(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<TimeEntry> $entries */
        $entries = $this->createQueryBuilder('e')
            ->addSelect('l', 'parent', 'p')
            ->join('e.lot', 'l')
            ->join('l.project', 'p')
            ->leftJoin('l.parent', 'parent')
            ->andWhere('e.user = :user')
            ->andWhere('e.day BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();

        return $entries;
    }

    /**
     * @return array<int, int> quarters entered on each lot of the project, by lot id
     */
    public function sumQuartersByLot(Project $project): array
    {
        /** @var list<array{lotId: int|string, quarters: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.lot) AS lotId', 'SUM(e.quarters) AS quarters')
            ->join('e.lot', 'l')
            ->andWhere('l.project = :project')
            ->setParameter('project', $project)
            ->groupBy('e.lot')
            ->getQuery()
            ->getArrayResult();

        $quarters = [];
        foreach ($rows as $row) {
            $quarters[(int) $row['lotId']] = (int) $row['quarters'];
        }

        return $quarters;
    }

    public function sumQuartersForLotId(int $lotId): int
    {
        $sum = $this->createQueryBuilder('e')
            ->select('COALESCE(SUM(e.quarters), 0)')
            ->andWhere('IDENTITY(e.lot) = :lot')
            ->setParameter('lot', $lotId)
            ->getQuery()
            ->getSingleScalarResult();

        return is_numeric($sum) ? (int) $sum : 0;
    }

    /**
     * @param list<Lot> $lots
     */
    public function existsForLots(array $lots): bool
    {
        $ids = array_values(array_filter(array_map(static fn (Lot $lot): ?int => $lot->getId(), $lots)));
        if ([] === $ids) {
            return false;
        }

        return [] !== $this->createQueryBuilder('e')
            ->select('e.id')
            ->andWhere('IDENTITY(e.lot) IN (:lots)')
            ->setParameter('lots', $ids, ArrayParameterType::INTEGER)
            ->setMaxResults(1)
            ->getQuery()
            ->getScalarResult();
    }

    public function existsForProject(Project $project): bool
    {
        return [] !== $this->createQueryBuilder('e')
            ->select('e.id')
            ->join('e.lot', 'l')
            ->andWhere('l.project = :project')
            ->setParameter('project', $project)
            ->setMaxResults(1)
            ->getQuery()
            ->getScalarResult();
    }

    public function moveToLot(Lot $from, Lot $to): void
    {
        $this->getEntityManager()->createQuery('UPDATE App\Entity\TimeEntry e SET e.lot = :to WHERE e.lot = :from')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->execute();
    }
}
