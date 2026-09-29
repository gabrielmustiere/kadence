<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\LotInput;
use App\Dto\ProjectInput;
use App\Entity\Lot;
use App\Entity\Project;
use App\Exception\LotDepthException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
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
     * to carry them (see LotInput::forSubLotOf()), and the lot, no longer a leaf, loses them.
     */
    public function addSubLot(Lot $parent, LotInput $input): Lot
    {
        if ($parent->isSubLot()) {
            throw new LotDepthException();
        }

        $subLot = new Lot($parent->getProject(), $parent);
        $this->applyLot($subLot, $input);
        $this->clearLeafData($parent);

        $this->entityManager->persist($subLot);
        $this->entityManager->flush();

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
        $parent = $lot->getParent();
        if (null !== $parent) {
            $parent->removeChild($lot);
            if ($parent->isLeaf()) {
                $parent->setEstimateDays($lot->getEstimateDays())->setOwner($lot->getOwner());
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
    }

    private function clearLeafData(Lot $lot): void
    {
        $lot->setEstimateDays(null)->setOwner(null);
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
