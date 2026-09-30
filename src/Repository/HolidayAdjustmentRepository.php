<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\HolidayAdjustment;
use App\Enum\Type\HolidayCalendar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HolidayAdjustment>
 */
class HolidayAdjustmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HolidayAdjustment::class);
    }

    /**
     * @return list<HolidayAdjustment> the adjustments of the calendar between two days included, in date order
     */
    public function findBetween(HolidayCalendar $calendar, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<HolidayAdjustment> $adjustments */
        $adjustments = $this->createQueryBuilder('a')
            ->andWhere('a.calendar = :calendar')
            ->andWhere('a.day BETWEEN :from AND :to')
            ->setParameter('calendar', $calendar)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->orderBy('a.day', 'ASC')
            ->getQuery()
            ->getResult();

        return $adjustments;
    }

    public function findOneAt(HolidayCalendar $calendar, \DateTimeImmutable $day): ?HolidayAdjustment
    {
        /** @var HolidayAdjustment|null $adjustment */
        $adjustment = $this->createQueryBuilder('a')
            ->andWhere('a.calendar = :calendar')
            ->andWhere('a.day = :day')
            ->setParameter('calendar', $calendar)
            ->setParameter('day', $day, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getOneOrNullResult();

        return $adjustment;
    }
}
