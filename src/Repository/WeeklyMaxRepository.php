<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\WeeklyMax;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeeklyMax>
 */
class WeeklyMaxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeeklyMax::class);
    }

    public function findInEffectAt(User $user, \DateTimeImmutable $monday): ?WeeklyMax
    {
        /** @var WeeklyMax|null $weeklyMax */
        $weeklyMax = $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.effectiveFrom <= :monday')
            ->setParameter('user', $user)
            ->setParameter('monday', $monday, Types::DATE_IMMUTABLE)
            ->orderBy('w.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $weeklyMax;
    }

    public function findOneAt(User $user, \DateTimeImmutable $monday): ?WeeklyMax
    {
        /** @var WeeklyMax|null $weeklyMax */
        $weeklyMax = $this->createQueryBuilder('w')
            ->andWhere('w.user = :user')
            ->andWhere('w.effectiveFrom = :monday')
            ->setParameter('user', $user)
            ->setParameter('monday', $monday, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getOneOrNullResult();

        return $weeklyMax;
    }

    /**
     * @return array<int, list<array{string, int<1, 20>}>> Monday of effect (Y-m-d) and quarters, oldest first, by user id
     */
    public function findAllQuartersByUser(): array
    {
        /** @var list<WeeklyMax> $weeklyMaxes */
        $weeklyMaxes = $this->createQueryBuilder('w')
            ->addSelect('u')
            ->join('w.user', 'u')
            ->orderBy('w.effectiveFrom', 'ASC')
            ->getQuery()
            ->getResult();

        $quarters = [];
        foreach ($weeklyMaxes as $weeklyMax) {
            $quarters[(int) $weeklyMax->getUser()->getId()][] = [$weeklyMax->getEffectiveFrom()->format('Y-m-d'), $weeklyMax->getQuarters()];
        }

        return $quarters;
    }

    /**
     * @return list<WeeklyMax> oldest first
     */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['effectiveFrom' => 'ASC']);
    }
}
