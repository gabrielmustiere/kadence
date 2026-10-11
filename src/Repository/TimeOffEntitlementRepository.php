<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TimeOffEntitlement;
use App\Entity\User;
use App\Model\TimeOff\TimeOffPeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeOffEntitlement>
 */
class TimeOffEntitlementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeOffEntitlement::class);
    }

    public function findOneFor(User $user, TimeOffPeriod $period): ?TimeOffEntitlement
    {
        return $this->findOneBy(['user' => $user, 'type' => $period->type, 'year' => $period->year]);
    }

    /**
     * @param list<User> $users
     *
     * @return array<int, array<string, int<0, max>>> half-days by user id, then by period key
     */
    public function findHalfDaysOf(array $users): array
    {
        $halfDays = [];
        foreach ($this->findBy(['user' => $users]) as $entitlement) {
            $halfDays[(int) $entitlement->getUser()->getId()][TimeOffPeriod::of($entitlement->getType(), $entitlement->getYear())->key()] = $entitlement->getHalfDays();
        }

        return $halfDays;
    }

    /**
     * @return array<int, TimeOffEntitlement> by user id
     */
    public function findForPeriod(TimeOffPeriod $period): array
    {
        $byUser = [];
        foreach ($this->findBy(['type' => $period->type, 'year' => $period->year]) as $entitlement) {
            $byUser[(int) $entitlement->getUser()->getId()] = $entitlement;
        }

        return $byUser;
    }
}
