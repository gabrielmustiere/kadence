<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TimeEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimeEntryRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_time_entry_user_lot_day', columns: ['user_id', 'lot_id', 'day'])]
#[ORM\Index(name: 'idx_time_entry_user_day', columns: ['user_id', 'day'])]
class TimeEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'lot_id', nullable: false)]
    private Lot $lot;

    #[ORM\Column(name: 'day', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $day;

    /** @var int<1, 4> */
    #[ORM\Column(name: 'quarters', type: Types::SMALLINT)]
    private int $quarters;

    /** @param int<1, 4> $quarters */
    public function __construct(User $user, Lot $lot, \DateTimeImmutable $day, int $quarters)
    {
        $this->user = $user;
        $this->lot = $lot;
        $this->day = $day->setTime(0, 0);
        $this->quarters = $quarters;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLot(): Lot
    {
        return $this->lot;
    }

    public function getDay(): \DateTimeImmutable
    {
        return $this->day;
    }

    /** @return int<1, 4> */
    public function getQuarters(): int
    {
        return $this->quarters;
    }

    /** @param int<1, 4> $quarters */
    public function setQuarters(int $quarters): static
    {
        $this->quarters = $quarters;

        return $this;
    }
}
