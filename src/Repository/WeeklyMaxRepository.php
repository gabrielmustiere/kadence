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
     * @return list<WeeklyMax> oldest first
     */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['effectiveFrom' => 'ASC']);
    }
}
