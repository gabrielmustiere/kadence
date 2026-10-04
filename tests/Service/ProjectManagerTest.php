<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\LotInput;
use App\Entity\Lot;
use App\Exception\LotHasTimeEntriesException;
use App\Repository\FavoriteLotRepository;
use App\Repository\LotProgressRepository;
use App\Repository\TimeEntryRepository;
use App\Service\ProjectManager;
use App\Tests\Support\CreatesFavorites;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectManagerTest extends KernelTestCase
{
    use CreatesFavorites;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    public function testTimeEnteredSinceTheCheckStillPreventsDeletingTheLeaf(): void
    {
        $leaf = $this->leafWithTime();
        $leafId = $leaf->getId();

        try {
            $this->managerCheckingBeforeTheTimeWasEntered()->deleteLot($leaf);
            self::fail('A leaf carrying time cannot be deleted.');
        } catch (LotHasTimeEntriesException $exception) {
            self::assertSame(\sprintf('Des temps sont saisis sur « %s » : il ne peut plus être supprimé.', $leaf->getTitle()), $exception->getMessage());
        }

        self::assertSame(1, $this->rowCount('SELECT COUNT(*) FROM lot WHERE id = ?', $leafId));
        self::assertSame(1, $this->rowCount('SELECT COUNT(*) FROM time_entry WHERE lot_id = ?', $leafId));
    }

    public function testTimeEnteredSinceTheCheckStillPreventsDeletingTheProject(): void
    {
        $leaf = $this->leafWithTime();
        $project = $leaf->getProject();
        $projectId = $project->getId();

        try {
            $this->managerCheckingBeforeTheTimeWasEntered()->deleteProject($project);
            self::fail('A project carrying time cannot be deleted.');
        } catch (LotHasTimeEntriesException $exception) {
            self::assertSame(\sprintf('Des temps sont saisis sur « %s » : il ne peut plus être supprimé.', $project->getTitle()), $exception->getMessage());
        }

        self::assertSame(1, $this->rowCount('SELECT COUNT(*) FROM project WHERE id = ?', $projectId));
        self::assertSame(1, $this->rowCount('SELECT COUNT(*) FROM time_entry WHERE lot_id = ?', $leaf->getId()));
    }

    public function testTheFirstSubLotTakesOverTheFavoritesOfItsLot(): void
    {
        $lot = $this->createLot($this->createProject());
        $this->createFavorite($this->createUser(), $lot);
        $this->createFavorite($this->createUser(), $lot);
        $input = LotInput::forSubLotOf($lot);
        $input->title = 'Écrans';

        $subLot = $this->manager()->addSubLot($lot, $input);

        self::assertSame(0, $this->rowCount('SELECT COUNT(*) FROM favorite_lot WHERE lot_id = ?', $lot->getId()));
        self::assertSame(2, $this->rowCount('SELECT COUNT(*) FROM favorite_lot WHERE lot_id = ?', $subLot->getId()));
    }

    public function testDeletingTheLastSubLotGivesItsFavoritesBackToItsLot(): void
    {
        $lot = $this->createLot($project = $this->createProject());
        $subLot = $this->createLot($project, parent: $lot);
        $this->createFavorite($this->createUser(), $subLot);

        $this->manager()->deleteLot($subLot);

        self::assertSame(1, $this->rowCount('SELECT COUNT(*) FROM favorite_lot WHERE lot_id = ?', $lot->getId()));
    }

    public function testAFavoriteNeverPreventsDeletingASplitLot(): void
    {
        $split = $this->createLot($project = $this->createProject());
        $subLot = $this->createLot($project, parent: $split);
        $this->createLot($project, parent: $split);
        $this->createFavorite($this->createUser(), $subLot);
        $subLotId = $subLot->getId();

        $this->manager()->deleteLot($split);

        self::assertSame(0, $this->rowCount('SELECT COUNT(*) FROM favorite_lot WHERE lot_id = ?', $subLotId));
    }

    public function testAFavoriteNeverPreventsDeletingAProject(): void
    {
        $lot = $this->createLot($project = $this->createProject());
        $this->createFavorite($this->createUser(), $lot);
        $lotId = $lot->getId();
        $projectId = $project->getId();

        $this->manager()->deleteProject($project);

        self::assertSame(0, $this->rowCount('SELECT COUNT(*) FROM favorite_lot WHERE lot_id = ?', $lotId));
        self::assertSame(0, $this->rowCount('SELECT COUNT(*) FROM project WHERE id = ?', $projectId));
    }

    private function leafWithTime(): Lot
    {
        $leaf = $this->createLot($this->createProject(), 5);
        $this->createTimeEntry($this->createUser(), $leaf, '2026-09-28', 4);

        return $leaf;
    }

    /**
     * Its check finds no time on the lot or the project, as if the time had been entered right after.
     */
    private function managerCheckingBeforeTheTimeWasEntered(): ProjectManager
    {
        $timeEntryRepository = $this->createStub(TimeEntryRepository::class);
        $timeEntryRepository->method('existsForLots')->willReturn(false);
        $timeEntryRepository->method('existsForProject')->willReturn(false);
        $lotProgressRepository = self::getContainer()->get(LotProgressRepository::class);
        \assert($lotProgressRepository instanceof LotProgressRepository);
        $favoriteLotRepository = self::getContainer()->get(FavoriteLotRepository::class);
        \assert($favoriteLotRepository instanceof FavoriteLotRepository);

        return new ProjectManager($this->entityManager(), $timeEntryRepository, $lotProgressRepository, $favoriteLotRepository);
    }

    private function manager(): ProjectManager
    {
        $manager = self::getContainer()->get(ProjectManager::class);
        \assert($manager instanceof ProjectManager);

        return $manager;
    }

    private function rowCount(string $sql, ?int $id): int
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        $count = $connection->fetchOne($sql, [$id]);
        \assert(is_numeric($count));

        return (int) $count;
    }
}
