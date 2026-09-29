<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\LotInput;
use App\Dto\ProjectInput;
use App\Entity\Lot;
use App\Entity\Project;
use App\Exception\LotDepthException;
use App\Exception\LotHasTimeEntriesException;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TimeEntryRepository $timeEntryRepository,
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

        $this->entityManager->remove($project);
        $this->entityManager->flush();
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
     * The estimate and owner of a lot receiving its first sub-lot move to that sub-lot: the input is expected
     * to carry them (see LotInput::forSubLotOf()), and the lot, no longer a leaf, loses them. Its time entries
     * and initial estimate move along.
     */
    public function addSubLot(Lot $parent, LotInput $input): Lot
    {
        if ($parent->isSubLot()) {
            throw new LotDepthException();
        }

        $takesOver = $parent->isLeaf();
        $hasTime = $takesOver && $this->timeEntryRepository->existsForLots([$parent]);

        $subLot = new Lot($parent->getProject(), $parent);
        $this->applyLot($subLot, $input);
        if ($takesOver) {
            $subLot->setInitialEstimateDays($parent->getInitialEstimateDays() ?? ($hasTime ? $subLot->getEstimateDays() : null));
        }
        $this->clearLeafData($parent);

        $this->entityManager->wrapInTransaction(function () use ($parent, $subLot, $hasTime): void {
            $this->entityManager->persist($subLot);
            $this->entityManager->flush();
            if ($hasTime) {
                $this->timeEntryRepository->moveToLot($parent, $subLot);
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
     * Removing the last sub-lot of a lot turns the lot back into a leaf that takes over the sub-lot's estimate and owner.
     */
    public function deleteLot(Lot $lot): void
    {
        if ($this->timeEntryRepository->existsForLots([$lot, ...$lot->getChildren()->getValues()])) {
            throw new LotHasTimeEntriesException($lot->getTitle());
        }

        $parent = $lot->getParent();
        if (null !== $parent) {
            $parent->removeChild($lot);
            if ($parent->isLeaf()) {
                $parent
                    ->setEstimateDays($lot->getEstimateDays())
                    ->setOwner($lot->getOwner())
                    ->setInitialEstimateDays($lot->getInitialEstimateDays());
            }
        }

        $project = $lot->getProject();
        foreach ($lot->getChildren() as $child) {
            $project->removeLot($child);
        }
        $project->removeLot($lot);

        $this->entityManager->remove($lot);
        $this->entityManager->flush();
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
            ->setOwner($input->owner);

        if (null === $lot->getInitialEstimateDays() && null !== $lot->getEstimateDays() && null !== $lot->getId()
            && $this->timeEntryRepository->existsForLots([$lot])) {
            $lot->setInitialEstimateDays($lot->getEstimateDays());
        }
    }

    private function clearLeafData(Lot $lot): void
    {
        $lot->setEstimateDays(null)->setOwner(null)->setInitialEstimateDays(null);
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
