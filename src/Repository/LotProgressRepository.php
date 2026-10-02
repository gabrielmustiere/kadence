<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LotProgress>
 */
class LotProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LotProgress::class);
    }

    /**
     * The last declaration of each lot.
     *
     * @return array<int, LotProgress> by lot id
     */
    public function findCurrentByLot(): array
    {
        /** @var list<LotProgress> $declarations */
        $declarations = $this->createQueryBuilder('p')
            ->andWhere('p.declaredOn = (SELECT MAX(p2.declaredOn) FROM ' . LotProgress::class . ' p2 WHERE p2.lot = p.lot)')
            ->getQuery()
            ->getResult();

        $current = [];
        foreach ($declarations as $declaration) {
            $current[(int) $declaration->getLot()->getId()] = $declaration;
        }

        return $current;
    }

    /**
     * Every declaration made on the lots of the project, with its author, the latest first.
     *
     * @return array<int, non-empty-list<LotProgress>> by lot id
     */
    public function findForProjectByLot(Project $project): array
    {
        /** @var list<LotProgress> $declarations */
        $declarations = $this->createQueryBuilder('p')
            ->addSelect('a')
            ->join('p.lot', 'l')
            ->join('p.author', 'a')
            ->andWhere('l.project = :project')
            ->setParameter('project', $project)
            ->orderBy('p.declaredOn', 'DESC')
            ->getQuery()
            ->getResult();

        $byLot = [];
        foreach ($declarations as $declaration) {
            $byLot[(int) $declaration->getLot()->getId()][] = $declaration;
        }

        return $byLot;
    }

    public function findOneByLotAndDay(Lot $lot, \DateTimeImmutable $day): ?LotProgress
    {
        return $this->findOneBy(['lot' => $lot, 'declaredOn' => $day->setTime(0, 0)]);
    }

    public function moveToLot(Lot $from, Lot $to): void
    {
        $this->getEntityManager()->createQuery('UPDATE ' . LotProgress::class . ' p SET p.lot = :to WHERE p.lot = :from')
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

        $this->getEntityManager()->createQuery('DELETE FROM ' . LotProgress::class . ' p WHERE IDENTITY(p.lot) IN (:lots)')
            ->setParameter('lots', $ids, ArrayParameterType::INTEGER)
            ->execute();
    }
}
