<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\FavoriteLot;
use App\Repository\FavoriteLotRepository;
use App\Service\FavoriteManager;
use App\Tests\Support\CreatesFavorites;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FavoriteManagerTest extends KernelTestCase
{
    use CreatesFavorites;
    use CreatesProjects;
    use CreatesUsers;

    public function testAddingTwiceKeepsASingleFavorite(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());

        $this->manager()->add($user, $lot);
        $this->manager()->add($user, $lot);

        self::assertSame([$lot], $this->repository()->findLotsOf($user));
        self::assertSame(1, $this->repository()->count(['user' => $user]));
    }

    public function testRemovingAFavoriteThatIsNotThereChangesNothing(): void
    {
        $user = $this->createUser();
        $kept = $this->createLot($this->createProject());
        $this->createFavorite($user, $kept);
        $removed = $this->createLot($this->createProject());

        $this->manager()->remove($user, $removed);
        $this->manager()->remove($user, $removed);

        self::assertSame([$kept], $this->repository()->findLotsOf($user));
    }

    public function testRemovingTakesOnlyTheFavoriteOfThatPerson(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $lot = $this->createLot($this->createProject());
        $this->createFavorite($user, $lot);
        $this->createFavorite($other, $lot);

        $this->manager()->remove($user, $lot);

        self::assertSame([], $this->repository()->findLotsOf($user));
        self::assertSame([$lot], $this->repository()->findLotsOf($other));
    }

    public function testASplitLotCannotBeAFavorite(): void
    {
        $lot = $this->createLot($project = $this->createProject());
        $this->createLot($project, parent: $lot);

        $this->expectException(\LogicException::class);

        new FavoriteLot($this->createUser(), $lot);
    }

    private function manager(): FavoriteManager
    {
        $manager = self::getContainer()->get(FavoriteManager::class);
        \assert($manager instanceof FavoriteManager);

        return $manager;
    }

    private function repository(): FavoriteLotRepository
    {
        $repository = self::getContainer()->get(FavoriteLotRepository::class);
        \assert($repository instanceof FavoriteLotRepository);

        return $repository;
    }
}
