<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LotRepository::class)]
class Lot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lots')]
    #[ORM\JoinColumn(name: 'project_id', nullable: false)]
    private Project $project;

    #[ORM\ManyToOne(inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    private ?Lot $parent;

    /** @var Collection<int, Lot> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['remove'])]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $children;

    /** @var non-empty-string|null */
    #[ORM\Column(length: 150)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** @var positive-int|null */
    #[ORM\Column(name: 'estimate_days', nullable: true)]
    private ?int $estimateDays = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'owner_id')]
    private ?User $owner = null;

    public function __construct(Project $project, ?self $parent = null)
    {
        $this->project = $project;
        $this->parent = $parent;
        $this->children = new ArrayCollection();

        $project->addLot($this);
        $parent?->addChild($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function isSubLot(): bool
    {
        return null !== $this->parent;
    }

    public function isLeaf(): bool
    {
        return $this->children->isEmpty();
    }

    /**
     * @return Collection<int, Lot>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        $this->children->removeElement($child);

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /** @param non-empty-string $title */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** @return positive-int|null */
    public function getEstimateDays(): ?int
    {
        return $this->estimateDays;
    }

    /** @param positive-int|null $estimateDays */
    public function setEstimateDays(?int $estimateDays): static
    {
        $this->estimateDays = $estimateDays;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }
}
