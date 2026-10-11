<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TimeOffEntitlement;
use App\Entity\User;
use App\Model\TimeOff\TimeOffPeriod;
use App\Repository\TimeOffEntitlementRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TimeOffEntitlementManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TimeOffEntitlementRepository $timeOffEntitlementRepository,
    ) {
    }

    /**
     * Records the entitlements of a period, without history: each value replaces the one declared before.
     *
     * @param iterable<array{User, int<0, max>}> $entitlements person and half-days
     */
    public function declare(TimeOffPeriod $period, iterable $entitlements): void
    {
        $existing = $this->timeOffEntitlementRepository->findForPeriod($period);
        foreach ($entitlements as [$user, $halfDays]) {
            $entitlement = $existing[(int) $user->getId()] ?? null;
            if (null !== $entitlement) {
                $entitlement->setHalfDays($halfDays);
            } else {
                $this->entityManager->persist(new TimeOffEntitlement($user, $period->type, $period->year, $halfDays));
            }
        }

        $this->entityManager->flush();
    }
}
