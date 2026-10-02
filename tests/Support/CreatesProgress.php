<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Entity\User;
use App\Model\Quarters;
use App\Model\Schedule\LeafProgress;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesProgress
{
    abstract private function entityManager(): EntityManagerInterface;

    /**
     * Records a declaration on a given day, anchored on the time entered then and the estimate of the leaf.
     *
     * @param int<0, 100> $percent
     * @param int<0, max> $enteredQuarters
     */
    private function createProgress(Lot $lot, User $author, string $day, int $percent, int $enteredQuarters): LotProgress
    {
        $remaining = 0 === $percent ? null : LeafProgress::anchoredRemaining($percent, $enteredQuarters, ($lot->getEstimateDays() ?? 0) * Quarters::PER_DAY);
        $progress = new LotProgress($lot, $author, new \DateTimeImmutable($day), $percent, $enteredQuarters, $remaining);

        $entityManager = $this->entityManager();
        $entityManager->persist($progress);
        $entityManager->flush();

        return $progress;
    }
}
