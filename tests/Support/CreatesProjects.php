<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Lot;
use App\Entity\LotMember;
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
        if (null !== $owner) {
            new LotMember($lot, $owner, 100);
        }

        $entityManager = $this->entityManager();
        $entityManager->persist($lot);
        $entityManager->flush();

        return $lot;
    }

    /**
     * Replaces the start date and the team of the leaf.
     *
     * @param list<array{User, int<25, 100>}> $members person and share
     */
    private function planLot(Lot $lot, ?\DateTimeImmutable $startDate, array $members): Lot
    {
        $lot->setStartDate($startDate);
        $shares = [];
        foreach ($members as [$user, $share]) {
            $shares[(int) $user->getId()] = [$user, $share];
        }
        foreach ($lot->getMembers()->toArray() as $member) {
            $userId = (int) $member->getUser()->getId();
            if (isset($shares[$userId])) {
                $member->setShare($shares[$userId][1]);
                unset($shares[$userId]);
            } else {
                $lot->removeMember($member);
            }
        }
        foreach ($shares as [$user, $share]) {
            new LotMember($lot, $user, $share);
        }

        $this->entityManager()->flush();

        return $lot;
    }
}
