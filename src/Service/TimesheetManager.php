<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Exception\TimeEntryRefusedException;
use App\Model\Quarters;
use App\Model\Week;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class TimesheetManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TimeEntryRepository $timeEntryRepository,
        private WeeklyMaxManager $weeklyMaxManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Sets the cell of the user on this leaf and day; 0 empties it. Lowering a cell is always accepted, even on a
     * week above a weekly maximum lowered since, so that it can still be corrected.
     *
     * @throws TimeEntryRefusedException
     */
    public function record(User $user, Lot $lot, \DateTimeImmutable $day, int $quarters): void
    {
        $day = $day->setTime(0, 0);
        $this->assertRecordable($lot, $day, $quarters);

        $week = Week::containing($day);
        [$entry, $otherQuartersOfDay, $otherQuartersOfWeek] = $this->splitWeek($user, $lot, $day, $week);

        if ($quarters > ($entry?->getQuarters() ?? 0)) {
            if ($otherQuartersOfDay + $quarters > Quarters::PER_DAY) {
                throw TimeEntryRefusedException::dayFull($day, Quarters::PER_DAY - $otherQuartersOfDay);
            }

            $maxQuarters = $this->weeklyMaxManager->quartersFor($user, $week);
            if ($otherQuartersOfWeek + $quarters > $maxQuarters) {
                throw TimeEntryRefusedException::weekFull($maxQuarters, $maxQuarters - $otherQuartersOfWeek);
            }
        }

        $this->write($user, $lot, $day, $entry, $quarters);
    }

    /**
     * @phpstan-assert int<0, 4> $quarters
     */
    private function assertRecordable(Lot $lot, \DateTimeImmutable $day, int $quarters): void
    {
        if ($quarters < 0 || $quarters > Quarters::PER_DAY) {
            throw TimeEntryRefusedException::invalidQuarters();
        }
        if (!$lot->isLeaf()) {
            throw TimeEntryRefusedException::notALeaf($lot->getTitle());
        }
        if ((int) $day->format('N') > 5) {
            throw TimeEntryRefusedException::weekend();
        }
        if ($day > $this->clock->now()->setTime(0, 0)) {
            throw TimeEntryRefusedException::future();
        }
    }

    /**
     * @return array{TimeEntry|null, int, int} the cell's entry, then the quarters of the other cells of its day and of its week
     */
    private function splitWeek(User $user, Lot $lot, \DateTimeImmutable $day, Week $week): array
    {
        $entry = null;
        $otherQuartersOfDay = 0;
        $otherQuartersOfWeek = 0;
        $date = $day->format('Y-m-d');

        foreach ($this->timeEntryRepository->findForUserBetween($user, $week->monday, $week->friday()) as $candidate) {
            $sameDay = $candidate->getDay()->format('Y-m-d') === $date;
            if ($sameDay && $candidate->getLot()->getId() === $lot->getId()) {
                $entry = $candidate;
                continue;
            }

            $otherQuartersOfWeek += $candidate->getQuarters();
            if ($sameDay) {
                $otherQuartersOfDay += $candidate->getQuarters();
            }
        }

        return [$entry, $otherQuartersOfDay, $otherQuartersOfWeek];
    }

    /**
     * @param int<0, 4> $quarters
     */
    private function write(User $user, Lot $lot, \DateTimeImmutable $day, ?TimeEntry $entry, int $quarters): void
    {
        if (0 === $quarters) {
            if (null !== $entry) {
                $this->entityManager->remove($entry);
            }
        } elseif (null !== $entry) {
            $entry->setQuarters($quarters);
        } else {
            $this->entityManager->persist(new TimeEntry($user, $lot, $day, $quarters));
        }

        if (0 < $quarters && null === $lot->getInitialEstimateDays()) {
            $lot->setInitialEstimateDays($lot->getEstimateDays());
        }

        $this->entityManager->flush();
    }
}
