<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WeeklyMaxRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WeeklyMaxRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_weekly_max_user_from', columns: ['user_id', 'effective_from'])]
class WeeklyMax
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    /** Always a Monday: the value applies from that week on. */
    #[ORM\Column(name: 'effective_from', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $effectiveFrom;

    /** @var int<1, 20> */
    #[ORM\Column(name: 'quarters', type: Types::SMALLINT)]
    private int $quarters;

    /** @param int<1, 20> $quarters */
    public function __construct(User $user, \DateTimeImmutable $effectiveFrom, int $quarters)
    {
        $this->user = $user;
        $this->effectiveFrom = $effectiveFrom->setTime(0, 0);
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

    public function getEffectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    /** @return int<1, 20> */
    public function getQuarters(): int
    {
        return $this->quarters;
    }

    /** @param int<1, 20> $quarters */
    public function setQuarters(int $quarters): static
    {
        $this->quarters = $quarters;

        return $this;
    }
}
