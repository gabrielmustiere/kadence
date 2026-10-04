<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FavoriteLotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FavoriteLotRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_favorite_lot_user_lot', columns: ['user_id', 'lot_id'])]
class FavoriteLot
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

    public function __construct(User $user, Lot $lot)
    {
        if (!$lot->isLeaf()) {
            throw new \LogicException('Only a leaf can be a favorite.');
        }

        $this->user = $user;
        $this->lot = $lot;
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
}
