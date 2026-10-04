<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\FavoriteLot;
use App\Entity\Lot;
use App\Entity\User;
use App\Repository\FavoriteLotRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Adding and removing are idempotent rather than a toggle, so that a gesture sent twice (two tabs, a double click)
 * never cancels itself.
 */
final readonly class FavoriteManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FavoriteLotRepository $favoriteLotRepository,
    ) {
    }

    public function add(User $user, Lot $lot): void
    {
        if ($this->favoriteLotRepository->isFavorite($user, $lot)) {
            return;
        }

        $this->entityManager->persist(new FavoriteLot($user, $lot));
        $this->entityManager->flush();
    }

    public function remove(User $user, Lot $lot): void
    {
        $this->favoriteLotRepository->removeFor($user, $lot);
    }
}
