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

    /**
     * @return array<int, array{int, string, string}> quarters entered, first and last day entered (Y-m-d), by lot id
     */
    public function summarizeByLot(): array
    {
        /** @var list<array{lotId: int|string, quarters: int|string, first: string, last: string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.lot) AS lotId', 'SUM(e.quarters) AS quarters', 'MIN(e.day) AS first', 'MAX(e.day) AS last')
            ->groupBy('e.lot')
            ->getQuery()
            ->getArrayResult();

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[(int) $row['lotId']] = [(int) $row['quarters'], $row['first'], $row['last']];
        }

        return $summaries;
    }

    /**
     * @return array<int, array<int, int>> quarters entered by each person, by lot id then user id
     */
    public function sumQuartersByLotAndUser(): array
    {
        /** @var list<array{lotId: int|string, userId: int|string, quarters: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.lot) AS lotId', 'IDENTITY(e.user) AS userId', 'SUM(e.quarters) AS quarters')
            ->groupBy('e.lot', 'e.user')
            ->getQuery()
            ->getArrayResult();

        $quarters = [];
        foreach ($rows as $row) {
            $quarters[(int) $row['lotId']][(int) $row['userId']] = (int) $row['quarters'];
        }

        return $quarters;
    }

    /**
     * @param list<int> $lotIds
     *
     * @return array<int, list<array{int, int}>> user id and quarters of each entry, in the order they were entered (day,
     *                                           then entry), by lot id
     */
    public function findQuartersInOrderForLots(array $lotIds): array
    {
        if ([] === $lotIds) {
            return [];
        }

        /** @var list<array{lotId: int|string, userId: int|string, quarters: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.lot) AS lotId', 'IDENTITY(e.user) AS userId', 'e.quarters AS quarters')
            ->andWhere('IDENTITY(e.lot) IN (:lots)')
            ->setParameter('lots', $lotIds, ArrayParameterType::INTEGER)
            ->orderBy('e.day', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $entries = [];
        foreach ($rows as $row) {
            $entries[(int) $row['lotId']][] = [(int) $row['userId'], (int) $row['quarters']];
        }

        return $entries;
    }

    /**
     * @param list<int> $lotIds
     *
     * @return array<int, array<string, int>> quarters entered each day (Y-m-d, in date order), by lot id
     */
    public function sumQuartersByDayForLots(array $lotIds): array
    {
        if ([] === $lotIds) {
            return [];
        }

        /** @var list<array{lotId: int|string, day: \DateTimeImmutable, quarters: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.lot) AS lotId', 'e.day AS day', 'SUM(e.quarters) AS quarters')
            ->andWhere('IDENTITY(e.lot) IN (:lots)')
            ->setParameter('lots', $lotIds, ArrayParameterType::INTEGER)
            ->groupBy('e.lot', 'e.day')
            ->orderBy('e.day', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $days = [];
        foreach ($rows as $row) {
            $days[(int) $row['lotId']][$row['day']->format('Y-m-d')] = (int) $row['quarters'];
        }

        return $days;
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
