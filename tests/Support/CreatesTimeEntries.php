<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Lot;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Entity\WeeklyMax;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesTimeEntries
{
    abstract private function entityManager(): EntityManagerInterface;

    /** @param int<1, 4> $quarters */
    private function createTimeEntry(User $user, Lot $lot, string $day, int $quarters): TimeEntry
    {
        $entry = new TimeEntry($user, $lot, new \DateTimeImmutable($day), $quarters);

        $entityManager = $this->entityManager();
        $entityManager->persist($entry);
        $entityManager->flush();

        return $entry;
    }

    /** @param int<1, 20> $quarters */
    private function createWeeklyMax(User $user, string $monday, int $quarters): WeeklyMax
    {
        $weeklyMax = new WeeklyMax($user, new \DateTimeImmutable($monday), $quarters);

        $entityManager = $this->entityManager();
        $entityManager->persist($weeklyMax);
        $entityManager->flush();

        return $weeklyMax;
    }
}
