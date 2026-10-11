<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Type\TimeOffType;
use App\Repository\TimeOffEntitlementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The days a person is entitled to for a type with a balance and a period, declared by the direction. A missing row
 * means no day.
 */
#[ORM\Entity(repositoryClass: TimeOffEntitlementRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_time_off_entitlement', columns: ['user_id', 'type', 'year'])]
class TimeOffEntitlement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    #[ORM\Column(length: 10, enumType: TimeOffType::class)]
    private TimeOffType $type;

    /** The year the period starts in: 2026 for the paid leave of 2026-2027 as for the RTT of 2026. */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $year;

    /** @var int<0, max> */
    #[ORM\Column(name: 'half_days')]
    private int $halfDays;

    /**
     * @param int<0, max> $halfDays
     */
    public function __construct(User $user, TimeOffType $type, int $year, int $halfDays)
    {
        $this->user = $user;
        $this->type = $type;
        $this->year = $year;
        $this->halfDays = $halfDays;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getType(): TimeOffType
    {
        return $this->type;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    /** @return int<0, max> */
    public function getHalfDays(): int
    {
        return $this->halfDays;
    }

    /** @param int<0, max> $halfDays */
    public function setHalfDays(int $halfDays): static
    {
        $this->halfDays = $halfDays;

        return $this;
    }
}
