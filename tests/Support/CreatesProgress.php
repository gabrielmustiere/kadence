<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesProgress
{
    abstract private function entityManager(): EntityManagerInterface;

    /**
     * Records a declaration on a given day.
     *
     * @param int<0, 100> $percent
     */
    private function createProgress(Lot $lot, User $author, string $day, int $percent): LotProgress
    {
        $progress = new LotProgress($lot, $author, new \DateTimeImmutable($day), $percent);

        $entityManager = $this->entityManager();
        $entityManager->persist($progress);
        $entityManager->flush();

        return $progress;
    }
}
