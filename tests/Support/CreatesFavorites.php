<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\FavoriteLot;
use App\Entity\Lot;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesFavorites
{
    abstract private function entityManager(): EntityManagerInterface;

    private function createFavorite(User $user, Lot $lot): FavoriteLot
    {
        $favorite = new FavoriteLot($user, $lot);

        $entityManager = $this->entityManager();
        $entityManager->persist($favorite);
        $entityManager->flush();

        return $favorite;
    }
}
