<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FavoriteLot;
use App\Entity\Lot;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriteLot>
 */
class FavoriteLotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteLot::class);
    }

    /**
     * The user's favorite leaves, with their parent lot and their project.
     *
     * @return list<Lot>
     */
    public function findLotsOf(User $user): array
    {
        /** @var list<FavoriteLot> $favorites */
        $favorites = $this->createQueryBuilder('f')
            ->addSelect('l', 'parent', 'p')
            ->join('f.lot', 'l')
            ->join('l.project', 'p')
            ->leftJoin('l.parent', 'parent')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        return array_map(static fn (FavoriteLot $favorite): Lot => $favorite->getLot(), $favorites);
    }

    public function isFavorite(User $user, Lot $lot): bool
    {
        return [] !== $this->createQueryBuilder('f')
            ->select('f.id')
            ->andWhere('f.user = :user')
            ->andWhere('f.lot = :lot')
            ->setParameter('user', $user)
            ->setParameter('lot', $lot)
            ->setMaxResults(1)
            ->getQuery()
            ->getScalarResult();
    }

    public function removeFor(User $user, Lot $lot): void
    {
        $this->getEntityManager()->createQuery('DELETE FROM ' . FavoriteLot::class . ' f WHERE f.user = :user AND f.lot = :lot')
            ->setParameter('user', $user)
            ->setParameter('lot', $lot)
            ->execute();
    }

    public function moveToLot(Lot $from, Lot $to): void
    {
        $this->getEntityManager()->createQuery('UPDATE ' . FavoriteLot::class . ' f SET f.lot = :to WHERE f.lot = :from')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->execute();
    }

    /**
     * @param list<Lot> $lots
     */
    public function deleteForLots(array $lots): void
    {
        $ids = array_values(array_filter(array_map(static fn (Lot $lot): ?int => $lot->getId(), $lots)));
        if ([] === $ids) {
            return;
        }

        $this->getEntityManager()->createQuery('DELETE FROM ' . FavoriteLot::class . ' f WHERE IDENTITY(f.lot) IN (:lots)')
            ->setParameter('lots', $ids, ArrayParameterType::INTEGER)
            ->execute();
    }
}
