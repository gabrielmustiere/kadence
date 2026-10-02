<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\LotInput;
use App\Dto\ProjectInput;
use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\LotHasTimeEntriesException;
use App\Repository\LotProgressRepository;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TimeEntryRepository $timeEntryRepository,
        private LotProgressRepository $lotProgressRepository,
    ) {
    }

    public function createProject(ProjectInput $input): Project
    {
        $project = new Project();
        $this->applyProject($project, $input);

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    public function updateProject(Project $project, ProjectInput $input): void
    {
        $this->applyProject($project, $input);
        $this->entityManager->flush();
    }

    public function deleteProject(Project $project): void
    {
        if ($this->timeEntryRepository->existsForProject($project)) {
            throw new LotHasTimeEntriesException($project->getTitle());
        }

        $this->entityManager->wrapInTransaction(function () use ($project): void {
            $this->lotProgressRepository->deleteForLots($project->getLots()->getValues());
            $this->entityManager->remove($project);
            $this->entityManager->flush();
        });
    }

    public function addLot(Project $project, LotInput $input): Lot
    {
        $lot = new Lot($project);
        $this->applyLot($lot, $input);

        $this->entityManager->persist($lot);
        $this->entityManager->flush();

        return $lot;
    }

    /**
     * The estimate, owner, start date and team of a lot receiving its first sub-lot move to that sub-lot: the input
     * is expected to carry them (see LotInput::forSubLotOf()), and the lot, no longer a leaf, loses them. Its time
     * entries, progress declarations and initial estimate move along.
     */
    public function addSubLot(Lot $parent, LotInput $input): Lot
    {
        $takesOver = $parent->isLeaf();
        $hasTime = $takesOver && $this->timeEntryRepository->existsForLots([$parent]);

        $subLot = new Lot($parent->getProject(), $parent);
        $this->applyLot($subLot, $input);
        if ($takesOver) {
            $subLot->setInitialEstimateDays($parent->getInitialEstimateDays());
        }
        if ($hasTime) {
            $subLot->freezeInitialEstimate();
        }
        $this->clearLeafData($parent);

        $this->entityManager->wrapInTransaction(function () use ($parent, $subLot, $takesOver, $hasTime): void {
            $this->entityManager->persist($subLot);
            $this->entityManager->flush();
            if ($hasTime) {
                $this->timeEntryRepository->moveToLot($parent, $subLot);
            }
            if ($takesOver) {
                $this->lotProgressRepository->moveToLot($parent, $subLot);
            }
        });

        return $subLot;
    }

    public function updateLot(Lot $lot, LotInput $input): void
    {
        $this->applyLot($lot, $input);
        $this->entityManager->flush();
    }

    /**
     * Removing the last sub-lot of a lot turns the lot back into a leaf that takes over the sub-lot's estimate, owner,
     * start date, team and progress declarations.
     */
    public function deleteLot(Lot $lot): void
    {
        if ($this->timeEntryRepository->existsForLots([$lot, ...$lot->getChildren()->getValues()])) {
            throw new LotHasTimeEntriesException($lot->getTitle());
        }

        $takenBackBy = $this->detach($lot);

        $this->entityManager->wrapInTransaction(function () use ($lot, $takenBackBy): void {
            if (null !== $takenBackBy) {
                $this->lotProgressRepository->moveToLot($lot, $takenBackBy);
            } else {
                $this->lotProgressRepository->deleteForLots([$lot, ...$lot->getChildren()->getValues()]);
            }
            $this->entityManager->remove($lot);
            $this->entityManager->flush();
        });
    }

    /**
     * Takes the lot and its sub-lots out of the project and the lot out of its parent.
     *
     * @return Lot|null the parent turned back into a leaf, if any
     */
    private function detach(Lot $lot): ?Lot
    {
        $parent = $lot->getParent();
        $parent?->removeChild($lot);

        $project = $lot->getProject();
        foreach ($lot->getChildren() as $child) {
            $project->removeLot($child);
        }
        $project->removeLot($lot);

        if (null === $parent || !$parent->isLeaf()) {
            return null;
        }

        $parent
            ->setEstimateDays($lot->getEstimateDays())
            ->setOwner($lot->getOwner())
            ->setInitialEstimateDays($lot->getInitialEstimateDays())
            ->setStartDate($lot->getStartDate());
        foreach ($lot->getMembers() as $member) {
            new LotMember($parent, $member->getUser(), $member->getShare());
        }

        return $parent;
    }

    private function applyProject(Project $project, ProjectInput $input): void
    {
        $project
            ->setTitle(self::required($input->title))
            ->setDescription(self::optional($input->description));
    }

    private function applyLot(Lot $lot, LotInput $input): void
    {
        $lot
            ->setTitle(self::required($input->title))
            ->setDescription(self::optional($input->description));

        if (!$lot->isLeaf()) {
            $this->clearLeafData($lot);

            return;
        }

        $lot
            ->setEstimateDays(self::positiveOrNull($input->estimateDays))
            ->setOwner($input->owner)
            ->setStartDate($input->startDate);
        self::applyMembers($lot, $input->effectiveMembers());

        if ($this->timeEntryRepository->existsForLots([$lot])) {
            $lot->freezeInitialEstimate();
        }
    }

    private function clearLeafData(Lot $lot): void
    {
        $lot->setEstimateDays(null)->setOwner(null)->setInitialEstimateDays(null)->setStartDate(null);
        self::applyMembers($lot, []);
    }

    /**
     * Updates the members in place rather than replacing them: a person removed and added back within one flush
     * would be inserted before being deleted, and break the uniqueness of a person in a team.
     *
     * @param list<array{User, int<25, 100>}> $members person and share
     */
    private static function applyMembers(Lot $lot, array $members): void
    {
        $shares = [];
        foreach ($members as [$user, $share]) {
            $shares[spl_object_id($user)] = [$user, $share];
        }

        foreach ($lot->getMembers()->toArray() as $member) {
            $key = spl_object_id($member->getUser());
            if (isset($shares[$key])) {
                $member->setShare($shares[$key][1]);
                unset($shares[$key]);
            } else {
                $lot->removeMember($member);
            }
        }

        foreach ($shares as [$user, $share]) {
            new LotMember($lot, $user, $share);
        }
    }

    /**
     * @return non-empty-string
     */
    private static function required(?string $value): string
    {
        if (null === $value || '' === $value) {
            throw new \LogicException('A validated input has a title.');
        }

        return $value;
    }

    private static function optional(?string $value): ?string
    {
        return '' === $value ? null : $value;
    }

    /**
     * @return positive-int|null
     */
    private static function positiveOrNull(?int $value): ?int
    {
        if (null !== $value && $value < 1) {
            throw new \LogicException('A validated lot input has a positive estimate.');
        }

        return $value;
    }
}
