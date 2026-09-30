<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_lot_member_lot_user', columns: ['lot_id', 'user_id'])]
class LotMember
{
    /** The parts of their capacity a person can give to a leaf, in percent. */
    public const array SHARES = [25, 50, 75, 100];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(name: 'lot_id', nullable: false)]
    private Lot $lot;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    /** @var int<25, 100> */
    #[ORM\Column(name: 'share', type: Types::SMALLINT)]
    private int $share;

    /** @param int<25, 100> $share */
    public function __construct(Lot $lot, User $user, int $share)
    {
        $this->lot = $lot;
        $this->user = $user;
        $this->share = $share;

        $lot->addMember($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLot(): Lot
    {
        return $this->lot;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /** @return int<25, 100> */
    public function getShare(): int
    {
        return $this->share;
    }

    /** @param int<25, 100> $share */
    public function setShare(int $share): static
    {
        $this->share = $share;

        return $this;
    }
}
