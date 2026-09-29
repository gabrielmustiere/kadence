<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesProjects
{
    abstract private function entityManager(): EntityManagerInterface;

    /** @param non-empty-string|null $title */
    private function createProject(?string $title = null): Project
    {
        $project = new Project()->setTitle($title ?? uniqid('Projet ', true));

        $entityManager = $this->entityManager();
        $entityManager->persist($project);
        $entityManager->flush();

        return $project;
    }

    /**
     * @param positive-int|null     $estimateDays
     * @param non-empty-string|null $title
     */
    private function createLot(Project $project, ?int $estimateDays = null, ?User $owner = null, ?Lot $parent = null, ?string $title = null): Lot
    {
        $lot = new Lot($project, $parent)
            ->setTitle($title ?? uniqid('Lot ', true))
            ->setEstimateDays($estimateDays)
            ->setOwner($owner);

        $entityManager = $this->entityManager();
        $entityManager->persist($lot);
        $entityManager->flush();

        return $lot;
    }
}
