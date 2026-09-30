<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\Project;
use App\Entity\User;
use App\Validator\EstimateCoversConsumed;
use App\Validator\PlanningFitsCapacity;
use App\Validator\UniqueLotTitle;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueLotTitle]
#[EstimateCoversConsumed]
#[PlanningFitsCapacity]
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

    public ?\DateTimeImmutable $startDate = null;

    /** @var list<LotMemberInput> */
    #[Assert\Valid]
    #[Assert\Unique(message: 'Une personne ne figure qu\'une fois dans l\'équipe.', normalizer: [LotMemberInput::class, 'identify'])]
    public array $members = [];

    /** The start date carried before this change, like the estimate. */
    public ?\DateTimeImmutable $currentStartDate = null;

    /** @var array<int, int<25, 100>> the shares carried before this change, by person (object id) */
    private array $currentShares = [];

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
            $input->takePlanningOf($parent);
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
        $input->takePlanningOf($lot);

        return $input;
    }

    /**
     * The team rows with a person and a valid share, plus the owner when missing: the owner is always a member, with
     * the share they had, or 100 % when they join the team.
     *
     * @return list<array{User, int<25, 100>}>
     */
    public function effectiveMembers(): array
    {
        $members = [];
        foreach ($this->members as $member) {
            $share = $member->share;
            if (null !== $member->user && \in_array($share, LotMember::SHARES, true)) {
                $members[spl_object_id($member->user)] = [$member->user, $share];
            }
        }

        $owner = $this->owner;
        if (null !== $owner && !isset($members[spl_object_id($owner)])) {
            $members[spl_object_id($owner)] = [$owner, $this->currentShares[spl_object_id($owner)] ?? 100];
        }

        return array_values($members);
    }

    /**
     * Whether the start date or the team differ from the ones carried before this change.
     */
    public function planningChanged(): bool
    {
        $shares = [];
        foreach ($this->effectiveMembers() as [$user, $share]) {
            $shares[spl_object_id($user)] = $share;
        }
        ksort($shares);
        $current = $this->currentShares;
        ksort($current);

        return $this->startDate?->format('Y-m-d') !== $this->currentStartDate?->format('Y-m-d') || $shares !== $current;
    }

    private function takePlanningOf(Lot $lot): void
    {
        $this->startDate = $this->currentStartDate = $lot->getStartDate();
        $this->members = [];
        foreach ($lot->getMembers() as $member) {
            $this->members[] = LotMemberInput::of($member->getUser(), $member->getShare());
            $this->currentShares[spl_object_id($member->getUser())] = $member->getShare();
        }
    }
}
