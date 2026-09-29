<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Validator\EstimateCoversConsumed;
use App\Validator\UniqueLotTitle;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueLotTitle]
#[EstimateCoversConsumed]
final class LotInput
{
    public ?int $id = null;

    public ?Project $project = null;

    public ?Lot $parent = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public ?string $title = null;

    public ?string $description = null;

    #[Assert\Positive]
    public ?int $estimateDays = null;

    public ?User $owner = null;

    /** The estimate carried before this change: by the edited lot, or by the lot a first sub-lot takes over. */
    public ?int $currentEstimateDays = null;

    public static function forLotOf(Project $project): self
    {
        $input = new self();
        $input->project = $project;

        return $input;
    }

    /**
     * The first sub-lot of a lot takes over the lot's estimate and owner.
     */
    public static function forSubLotOf(Lot $parent): self
    {
        $input = new self();
        $input->project = $parent->getProject();
        $input->parent = $parent;

        if ($parent->isLeaf()) {
            $input->estimateDays = $parent->getEstimateDays();
            $input->owner = $parent->getOwner();
            $input->currentEstimateDays = $parent->getEstimateDays();
        }

        return $input;
    }

    public static function fromLot(Lot $lot): self
    {
        $input = new self();
        $input->id = $lot->getId();
        $input->project = $lot->getProject();
        $input->parent = $lot->getParent();
        $input->title = $lot->getTitle();
        $input->description = $lot->getDescription();
        $input->estimateDays = $lot->getEstimateDays();
        $input->owner = $lot->getOwner();
        $input->currentEstimateDays = $lot->getEstimateDays();

        return $input;
    }
}
