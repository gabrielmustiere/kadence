<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\WeeklyMax;
use App\Model\Week;
use App\Model\WeeklyMaximum;
use App\Repository\WeeklyMaxRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class WeeklyMaxManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WeeklyMaxRepository $weeklyMaxRepository,
    ) {
    }

    /**
     * @return int<1, 20>
     */
    public function quartersFor(User $user, Week $week): int
    {
        if (null === $user->getId()) {
            return WeeklyMaximum::DEFAULT_QUARTERS;
        }

        return $this->weeklyMaxRepository->findInEffectAt($user, $week->monday)?->getQuarters() ?? WeeklyMaximum::DEFAULT_QUARTERS;
    }

    /**
     * The maximum of the week once its holidays are left out.
     *
     * @return int<0, 20>
     */
    public function capFor(User $user, Week $week, int $holidayCount): int
    {
        return WeeklyMaximum::cap($this->quartersFor($user, $week), $holidayCount);
    }

    /**
     * Records the value from the given week on, unless it is already the one in effect; the caller flushes.
     *
     * @param int<1, 20> $quarters
     */
    public function change(User $user, int $quarters, Week $from): void
    {
        if ($this->quartersFor($user, $from) === $quarters) {
            return;
        }

        $existing = null === $user->getId() ? null : $this->weeklyMaxRepository->findOneAt($user, $from->monday);
        if (null !== $existing) {
            $existing->setQuarters($quarters);

            return;
        }

        $this->entityManager->persist(new WeeklyMax($user, $from->monday, $quarters));
    }

    public function delete(WeeklyMax $weeklyMax): void
    {
        $this->entityManager->remove($weeklyMax);
        $this->entityManager->flush();
    }
}
