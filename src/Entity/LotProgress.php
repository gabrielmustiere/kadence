<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LotProgressRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LotProgressRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_lot_progress_lot_day', columns: ['lot_id', 'declared_on'])]
class LotProgress
{
    /** The progress a leaf can be declared at, in percent: 0 withdraws it. */
    public const array PERCENTS = [0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'lot_id', nullable: false)]
    private Lot $lot;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'author_id', nullable: false)]
    private User $author;

    #[ORM\Column(name: 'declared_on', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $declaredOn;

    /** @var int<0, 100> */
    #[ORM\Column(name: 'percent', type: Types::SMALLINT)]
    private int $percent;

    /** @var int<0, max> time entered on the leaf when the progress was declared */
    #[ORM\Column(name: 'entered_quarters')]
    private int $enteredQuarters;

    /** @var int<0, max>|null what was left to do when the progress was declared, null at 0 % */
    #[ORM\Column(name: 'remaining_quarters', nullable: true)]
    private ?int $remainingQuarters;

    /**
     * @param int<0, 100>      $percent
     * @param int<0, max>      $enteredQuarters
     * @param int<0, max>|null $remainingQuarters
     */
    public function __construct(Lot $lot, User $author, \DateTimeImmutable $declaredOn, int $percent, int $enteredQuarters, ?int $remainingQuarters)
    {
        $this->lot = $lot;
        $this->author = $author;
        $this->declaredOn = $declaredOn->setTime(0, 0);
        $this->percent = $percent;
        $this->enteredQuarters = $enteredQuarters;
        $this->remainingQuarters = $remainingQuarters;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLot(): Lot
    {
        return $this->lot;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function getDeclaredOn(): \DateTimeImmutable
    {
        return $this->declaredOn;
    }

    /** @return int<0, 100> */
    public function getPercent(): int
    {
        return $this->percent;
    }

    /** @return int<0, max> */
    public function getEnteredQuarters(): int
    {
        return $this->enteredQuarters;
    }

    /** @return int<0, max>|null */
    public function getRemainingQuarters(): ?int
    {
        return $this->remainingQuarters;
    }

    /**
     * Replaces a declaration made earlier the same day.
     *
     * @param int<0, 100>      $percent
     * @param int<0, max>      $enteredQuarters
     * @param int<0, max>|null $remainingQuarters
     */
    public function redeclare(User $author, int $percent, int $enteredQuarters, ?int $remainingQuarters): static
    {
        $this->author = $author;
        $this->percent = $percent;
        $this->enteredQuarters = $enteredQuarters;
        $this->remainingQuarters = $remainingQuarters;

        return $this;
    }
}
