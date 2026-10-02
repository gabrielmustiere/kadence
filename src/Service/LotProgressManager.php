<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Entity\User;
use App\Exception\LotProgressRefusedException;
use App\Model\Quarters;
use App\Model\Schedule\LeafProgress;
use App\Repository\LotProgressRepository;
use App\Repository\TimeEntryRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class LotProgressManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LotProgressRepository $lotProgressRepository,
        private TimeEntryRepository $timeEntryRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Declares the progress of an estimated leaf today, anchoring what is left to do on the time entered so far; a
     * declaration made earlier the same day is replaced.
     *
     * @throws LotProgressRefusedException when the percent is not a step of 5, a leaf without time is declared complete,
     *                                     or someone else declared the progress of the leaf meanwhile today
     */
    public function declare(Lot $leaf, int $percent, User $author): LotProgress
    {
        $estimateDays = $leaf->getEstimateDays();
        if (!$leaf->isLeaf() || null === $estimateDays) {
            throw new \LogicException('Only an estimated leaf has a progress.');
        }
        if (!\in_array($percent, LotProgress::PERCENTS, true)) {
            throw LotProgressRefusedException::invalidPercent();
        }

        $entered = max(0, $this->timeEntryRepository->sumQuartersForLotId((int) $leaf->getId()));
        if (100 === $percent && 0 === $entered) {
            throw LotProgressRefusedException::completeWithoutTime($leaf->getTitle());
        }

        $remaining = 0 === $percent ? null : LeafProgress::anchoredRemaining($percent, $entered, $estimateDays * Quarters::PER_DAY);
        $today = $this->clock->now()->setTime(0, 0);
        $declaration = $this->lotProgressRepository->findOneByLotAndDay($leaf, $today);
        if (null === $declaration) {
            $declaration = new LotProgress($leaf, $author, $today, $percent, $entered, $remaining);
            $this->entityManager->persist($declaration);
        } else {
            $declaration->redeclare($author, $percent, $entered, $remaining);
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw LotProgressRefusedException::declaredMeanwhile($leaf->getTitle());
        }

        return $declaration;
    }
}
