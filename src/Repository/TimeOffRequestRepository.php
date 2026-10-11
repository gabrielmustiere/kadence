<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\TimeOffStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeOffRequest>
 */
class TimeOffRequestRepository extends ServiceEntityRepository
{
    private const array ACTIVE = [TimeOffStatus::Pending, TimeOffStatus::Approved];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeOffRequest::class);
    }

    /**
     * The pending and approved requests of the person that overlap these days.
     *
     * @return list<TimeOffRequest> in start order
     */
    public function findActiveOfUserBetween(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<TimeOffRequest> $requests */
        $requests = $this->overlapping($from, $to)
            ->andWhere('r.user = :user')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', self::ACTIVE)
            ->getQuery()
            ->getResult();

        return $requests;
    }

    /**
     * @return list<TimeOffRequest>
     */
    public function findActiveOfUser(User $user): array
    {
        return $this->findBy(['user' => $user, 'status' => self::ACTIVE], ['startDay' => 'ASC']);
    }

    /**
     * The pending and approved requests of everyone that overlap these days, with their person.
     *
     * @return list<TimeOffRequest> in start order
     */
    public function findActiveBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<TimeOffRequest> $requests */
        $requests = $this->overlapping($from, $to)
            ->addSelect('u')
            ->join('r.user', 'u')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('statuses', self::ACTIVE)
            ->getQuery()
            ->getResult();

        return $requests;
    }

    /**
     * The approved requests of everyone that overlap these days.
     *
     * @return list<TimeOffRequest> in start order
     */
    public function findApprovedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<TimeOffRequest> $requests */
        $requests = $this->overlapping($from, $to)
            ->andWhere('r.status = :status')
            ->setParameter('status', TimeOffStatus::Approved)
            ->getQuery()
            ->getResult();

        return $requests;
    }

    /**
     * The approved requests of the person, from the oldest.
     *
     * @return list<TimeOffRequest>
     */
    public function findApprovedOfUser(User $user): array
    {
        return $this->findBy(['user' => $user, 'status' => TimeOffStatus::Approved], ['startDay' => 'ASC']);
    }

    /**
     * Every request of the person, the latest start first.
     *
     * @return list<TimeOffRequest>
     */
    public function findOfUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['startDay' => 'DESC', 'createdAt' => 'DESC']);
    }

    /**
     * @return list<TimeOffRequest>
     */
    public function findPendingOfUser(User $user): array
    {
        return $this->findBy(['user' => $user, 'status' => TimeOffStatus::Pending]);
    }

    /**
     * The pending requests a person can decide on, with their requester: those of the people they manage, or every one
     * for the direction; never their own.
     *
     * @return list<TimeOffRequest> in start order
     */
    public function findPendingDecidableBy(User $viewer, bool $direction): array
    {
        /** @var list<TimeOffRequest> $requests */
        $requests = $this->pendingDecidableBy($viewer, $direction)
            ->addSelect('u')
            ->orderBy('r.startDay', 'ASC')
            ->addOrderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $requests;
    }

    public function countPendingDecidableBy(User $viewer, bool $direction): int
    {
        return (int) $this->pendingDecidableBy($viewer, $direction)
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function pendingDecidableBy(User $viewer, bool $direction): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->join('r.user', 'u')
            ->andWhere('r.status = :status')
            ->andWhere('r.user != :viewer')
            ->setParameter('status', TimeOffStatus::Pending)
            ->setParameter('viewer', $viewer);

        if (!$direction) {
            $queryBuilder->andWhere('u.manager = :viewer');
        }

        return $queryBuilder;
    }

    private function overlapping(\DateTimeImmutable $from, \DateTimeImmutable $to): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.startDay <= :to')
            ->andWhere('r.endDay >= :from')
            ->setParameter('from', $from->setTime(0, 0), 'date_immutable')
            ->setParameter('to', $to->setTime(0, 0), 'date_immutable')
            ->orderBy('r.startDay', 'ASC');
    }
}
