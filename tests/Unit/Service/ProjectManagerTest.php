<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\LotInput;
use App\Dto\LotMemberInput;
use App\Dto\ProjectInput;
use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\LotDepthException;
use App\Exception\LotHasTimeEntriesException;
use App\Repository\FavoriteLotRepository;
use App\Repository\LotProgressRepository;
use App\Repository\TimeEntryRepository;
use App\Service\ProjectManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ProjectManagerTest extends TestCase
{
    public function testCreateProjectPersistsIt(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with(self::isInstanceOf(Project::class));
        $entityManager->expects($this->once())->method('flush');
        $input = new ProjectInput();
        $input->title = 'Kadence';
        $input->description = '';

        $project = new ProjectManager($entityManager, $this->createStub(TimeEntryRepository::class), $this->createStub(LotProgressRepository::class), $this->createStub(FavoriteLotRepository::class))->createProject($input);

        self::assertSame('Kadence', $project->getTitle());
        self::assertNull($project->getDescription());
    }

    public function testAddLotAttachesALeafToTheProject(): void
    {
        $project = $this->project();
        $owner = $this->user();

        $lot = $this->manager()->addLot($project, $this->lotInput('Socle', 5, $owner));

        self::assertSame($project, $lot->getProject());
        self::assertFalse($lot->isSubLot());
        self::assertSame(5, $lot->getEstimateDays());
        self::assertSame($owner, $lot->getOwner());
        self::assertTrue($project->getLots()->contains($lot));
    }

    public function testFirstSubLotTakesOverTheEstimateAndOwnerOfItsLot(): void
    {
        $owner = $this->user();
        $lot = $this->lot($this->project(), 8, $owner);
        $input = LotInput::forSubLotOf($lot);
        $input->title = 'Modèle';

        $subLot = $this->manager()->addSubLot($lot, $input);

        self::assertSame(8, $subLot->getEstimateDays());
        self::assertSame($owner, $subLot->getOwner());
        self::assertSame($lot, $subLot->getParent());
        self::assertSame($lot->getProject(), $subLot->getProject());
        self::assertNull($lot->getEstimateDays());
        self::assertNull($lot->getOwner());
    }

    public function testNextSubLotsAreNotPrefilled(): void
    {
        $lot = $this->lot($this->project(), 8, $this->user());
        $this->manager()->addSubLot($lot, $this->prefilledSubLotInput($lot, 'Modèle'));

        $input = LotInput::forSubLotOf($lot);

        self::assertNull($input->estimateDays);
        self::assertNull($input->owner);
    }

    public function testSubLotCannotBeSplit(): void
    {
        $lot = $this->lot($this->project(), 8, null);
        $subLot = new Lot($lot->getProject(), $lot)->setTitle('Modèle');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $this->expectException(LotDepthException::class);

        new ProjectManager($entityManager, $this->createStub(TimeEntryRepository::class), $this->createStub(LotProgressRepository::class), $this->createStub(FavoriteLotRepository::class))->addSubLot($subLot, $this->lotInput('Trop profond', 1, null));
    }

    public function testDeletingTheLastSubLotMovesItsEstimateAndOwnerBackToItsLot(): void
    {
        $owner = $this->user();
        $lot = $this->lot($this->project(), null, null);
        $subLot = $this->subLot($lot, 3, $owner);

        $this->manager()->deleteLot($subLot);

        self::assertTrue($lot->isLeaf());
        self::assertSame(3, $lot->getEstimateDays());
        self::assertSame($owner, $lot->getOwner());
        self::assertFalse($lot->getProject()->getLots()->contains($subLot));
    }

    public function testDeletingOneOfSeveralSubLotsMovesNothingBack(): void
    {
        $lot = $this->lot($this->project(), null, null);
        $subLot = $this->subLot($lot, 3, $this->user());
        $this->subLot($lot, 2, $this->user());

        $this->manager()->deleteLot($subLot);

        self::assertFalse($lot->isLeaf());
        self::assertNull($lot->getEstimateDays());
        self::assertNull($lot->getOwner());
    }

    public function testDeletingASplitLotRemovesItsSubLotsFromTheProject(): void
    {
        $project = $this->project();
        $lot = $this->lot($project, null, null);
        $subLot = $this->subLot($lot, 3, null);

        $this->manager()->deleteLot($lot);

        self::assertCount(0, $project->getLots());
        self::assertFalse($project->getLots()->contains($subLot));
    }

    public function testASplitLotNeverCarriesAnEstimateOrAnOwner(): void
    {
        $lot = $this->lot($this->project(), null, null);
        $this->subLot($lot, 3, null);
        $input = LotInput::fromLot($lot);
        $input->title = 'Renommé';
        $input->estimateDays = 12;
        $input->owner = $this->user();

        $this->manager()->updateLot($lot, $input);

        self::assertSame('Renommé', $lot->getTitle());
        self::assertNull($lot->getEstimateDays());
        self::assertNull($lot->getOwner());
    }

    public function testFirstSubLotOfALotWithTimeTakesOverItsTimeAndInitialEstimate(): void
    {
        $lot = $this->lot($this->project(), 8, $this->user())->setInitialEstimateDays(6);
        $input = $this->prefilledSubLotInput($lot, 'Modèle');
        $timeEntryRepository = $this->createMock(TimeEntryRepository::class);
        $timeEntryRepository->method('existsForLots')->willReturn(true);
        $timeEntryRepository->expects($this->once())->method('moveToLot')->with($lot, self::isInstanceOf(Lot::class));

        $subLot = new ProjectManager($this->transactionalEntityManager(), $timeEntryRepository, $this->createStub(LotProgressRepository::class), $this->createStub(FavoriteLotRepository::class))->addSubLot($lot, $input);

        self::assertSame(6, $subLot->getInitialEstimateDays());
        self::assertSame(8, $subLot->getEstimateDays());
        self::assertNull($lot->getInitialEstimateDays());
    }

    public function testFirstSubLotOfALotWithTimeButNoInitialEstimateStartsItWithItsEstimate(): void
    {
        $lot = $this->lot($this->project(), null, null);
        $input = $this->prefilledSubLotInput($lot, 'Modèle');
        $input->estimateDays = 5;
        $timeEntryRepository = $this->createMock(TimeEntryRepository::class);
        $timeEntryRepository->method('existsForLots')->willReturn(true);
        $timeEntryRepository->expects($this->once())->method('moveToLot');

        $subLot = new ProjectManager($this->transactionalEntityManager(), $timeEntryRepository, $this->createStub(LotProgressRepository::class), $this->createStub(FavoriteLotRepository::class))->addSubLot($lot, $input);

        self::assertSame(5, $subLot->getInitialEstimateDays());
    }

    public function testFirstSubLotOfALotWithoutTimeMovesNoTime(): void
    {
        $lot = $this->lot($this->project(), 8, null);
        $timeEntryRepository = $this->createMock(TimeEntryRepository::class);
        $timeEntryRepository->method('existsForLots')->willReturn(false);
        $timeEntryRepository->expects($this->never())->method('moveToLot');

        $subLot = new ProjectManager($this->transactionalEntityManager(), $timeEntryRepository, $this->createStub(LotProgressRepository::class), $this->createStub(FavoriteLotRepository::class))->addSubLot($lot, $this->prefilledSubLotInput($lot, 'Modèle'));

        self::assertNull($subLot->getInitialEstimateDays());
    }

    public function testDeletingTheLastSubLotMovesItsInitialEstimateBack(): void
    {
        $lot = $this->lot($this->project(), null, null);
        $subLot = $this->subLot($lot, 3, null)->setInitialEstimateDays(2);

        $this->manager()->deleteLot($subLot);

        self::assertSame(2, $lot->getInitialEstimateDays());
    }

    public function testDeletingALotCarryingTimeIsRefused(): void
    {
        $lot = $this->lot($this->project(), null, null);
        $subLot = $this->subLot($lot, 3, null);
        $timeEntryRepository = $this->createMock(TimeEntryRepository::class);
        $timeEntryRepository->expects($this->once())->method('existsForLots')->with([$lot, $subLot])->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');

        $this->expectException(LotHasTimeEntriesException::class);

        new ProjectManager($entityManager, $timeEntryRepository, $this->createStub(LotProgressRepository::class), $this->createStub(FavoriteLotRepository::class))->deleteLot($lot);
    }

    public function testDeletingAProjectCarryingTimeIsRefused(): void
    {
        $project = $this->project();
        $timeEntryRepository = $this->createStub(TimeEntryRepository::class);
        $timeEntryRepository->method('existsForProject')->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');

        $this->expectException(LotHasTimeEntriesException::class);

        new ProjectManager($entityManager, $timeEntryRepository, $this->createStub(LotProgressRepository::class), $this->createStub(FavoriteLotRepository::class))->deleteProject($project);
    }

    public function testFirstSubLotTakesOverTheProgressOfItsLot(): void
    {
        $lot = $this->lot($this->project(), 8, $this->user());
        $lotProgressRepository = $this->createMock(LotProgressRepository::class);
        $lotProgressRepository->expects($this->once())->method('moveToLot')->with($lot, self::isInstanceOf(Lot::class));

        new ProjectManager($this->transactionalEntityManager(), $this->createStub(TimeEntryRepository::class), $lotProgressRepository, $this->createStub(FavoriteLotRepository::class))->addSubLot($lot, $this->prefilledSubLotInput($lot, 'Modèle'));
    }

    public function testNextSubLotsTakeOverNoProgress(): void
    {
        $lot = $this->lot($this->project(), null, null);
        $this->subLot($lot, 3, null);
        $lotProgressRepository = $this->createMock(LotProgressRepository::class);
        $lotProgressRepository->expects($this->never())->method('moveToLot');

        new ProjectManager($this->transactionalEntityManager(), $this->createStub(TimeEntryRepository::class), $lotProgressRepository, $this->createStub(FavoriteLotRepository::class))->addSubLot($lot, $this->lotInput('Écrans', 2, null));
    }

    public function testDeletingTheLastSubLotMovesItsProgressBackToItsLot(): void
    {
        $lot = $this->lot($this->project(), null, null);
        $subLot = $this->subLot($lot, 3, null);
        $lotProgressRepository = $this->createMock(LotProgressRepository::class);
        $lotProgressRepository->expects($this->once())->method('moveToLot')->with($subLot, $lot);
        $lotProgressRepository->expects($this->never())->method('deleteForLots');

        new ProjectManager($this->transactionalEntityManager(), $this->createStub(TimeEntryRepository::class), $lotProgressRepository, $this->createStub(FavoriteLotRepository::class))->deleteLot($subLot);
    }

    public function testDeletingASplitLotDeletesTheProgressOfItsSubLots(): void
    {
        $lot = $this->lot($this->project(), null, null);
        $subLot = $this->subLot($lot, 3, null);
        $lotProgressRepository = $this->createMock(LotProgressRepository::class);
        $lotProgressRepository->expects($this->once())->method('deleteForLots')->with([$lot, $subLot]);
        $lotProgressRepository->expects($this->never())->method('moveToLot');

        new ProjectManager($this->transactionalEntityManager(), $this->createStub(TimeEntryRepository::class), $lotProgressRepository, $this->createStub(FavoriteLotRepository::class))->deleteLot($lot);
    }

    public function testDeletingAProjectDeletesTheProgressOfItsLots(): void
    {
        $project = $this->project();
        $lot = $this->lot($project, null, null);
        $subLot = $this->subLot($lot, 3, null);
        $lotProgressRepository = $this->createMock(LotProgressRepository::class);
        $lotProgressRepository->expects($this->once())->method('deleteForLots')->with([$lot, $subLot]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn (callable $work): mixed => $work());
        $entityManager->expects($this->once())->method('remove')->with($project);

        new ProjectManager($entityManager, $this->createStub(TimeEntryRepository::class), $lotProgressRepository, $this->createStub(FavoriteLotRepository::class))->deleteProject($project);
    }

    public function testAddLotAppliesTheStartDateAndTheTeamWithTheOwnerAtFullShare(): void
    {
        $owner = $this->user();
        $member = $this->user();
        $input = $this->lotInput('Socle', 5, $owner);
        $input->startDate = new \DateTimeImmutable('2026-10-05 15:00');
        $input->members = [LotMemberInput::of($member, 50)];

        $lot = $this->manager()->addLot($this->project(), $input);

        self::assertSame('2026-10-05 00:00', $lot->getStartDate()?->format('Y-m-d H:i'));
        self::assertSame([[$member, 50], [$owner, 100]], $this->team($lot));
    }

    public function testUpdateLotChangesSharesAndRemovesMembersInPlace(): void
    {
        $owner = $this->user();
        $leaving = $this->user();
        $lot = $this->lot($this->project(), 5, $owner);
        $ownerMember = new LotMember($lot, $owner, 100);
        new LotMember($lot, $leaving, 50);
        $input = LotInput::fromLot($lot);
        $input->members = [LotMemberInput::of($owner, 75)];

        $this->manager()->updateLot($lot, $input);

        self::assertSame([[$owner, 75]], $this->team($lot));
        self::assertSame($ownerMember, $lot->getMembers()->first(), 'The remaining member is updated, not replaced.');
    }

    public function testFirstSubLotTakesOverTheStartDateAndTheTeamOfItsLot(): void
    {
        $owner = $this->user();
        $member = $this->user();
        $lot = $this->lot($this->project(), 8, $owner)->setStartDate(new \DateTimeImmutable('2026-10-05'));
        new LotMember($lot, $owner, 100);
        new LotMember($lot, $member, 25);

        $subLot = $this->manager()->addSubLot($lot, $this->prefilledSubLotInput($lot, 'Modèle'));

        self::assertSame('2026-10-05', $subLot->getStartDate()?->format('Y-m-d'));
        self::assertSame([[$owner, 100], [$member, 25]], $this->team($subLot));
        self::assertNull($lot->getStartDate());
        self::assertCount(0, $lot->getMembers());
    }

    public function testDeletingTheLastSubLotMovesItsStartDateAndTeamBackToItsLot(): void
    {
        $owner = $this->user();
        $lot = $this->lot($this->project(), null, null);
        $subLot = $this->subLot($lot, 3, $owner)->setStartDate(new \DateTimeImmutable('2026-10-05'));
        new LotMember($subLot, $owner, 50);

        $this->manager()->deleteLot($subLot);

        self::assertSame('2026-10-05', $lot->getStartDate()?->format('Y-m-d'));
        self::assertSame([[$owner, 50]], $this->team($lot));
    }

    /**
     * @return list<array{User, int}>
     */
    private function team(Lot $lot): array
    {
        return array_map(static fn (LotMember $member): array => [$member->getUser(), $member->getShare()], $lot->getMembers()->getValues());
    }

    private function manager(): ProjectManager
    {
        return new ProjectManager($this->createStub(EntityManagerInterface::class), $this->createStub(TimeEntryRepository::class), $this->createStub(LotProgressRepository::class), $this->createStub(FavoriteLotRepository::class));
    }

    private function transactionalEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn (callable $work): mixed => $work());

        return $entityManager;
    }

    private function project(): Project
    {
        return new Project()->setTitle('Kadence');
    }

    /** @param positive-int|null $estimateDays */
    private function lot(Project $project, ?int $estimateDays, ?User $owner): Lot
    {
        return new Lot($project)->setTitle('Lot')->setEstimateDays($estimateDays)->setOwner($owner);
    }

    /** @param positive-int|null $estimateDays */
    private function subLot(Lot $lot, ?int $estimateDays, ?User $owner): Lot
    {
        return new Lot($lot->getProject(), $lot)->setTitle('Sous-lot')->setEstimateDays($estimateDays)->setOwner($owner);
    }

    private function lotInput(string $title, ?int $estimateDays, ?User $owner): LotInput
    {
        $input = new LotInput();
        $input->title = $title;
        $input->estimateDays = $estimateDays;
        $input->owner = $owner;

        return $input;
    }

    private function prefilledSubLotInput(Lot $lot, string $title): LotInput
    {
        $input = LotInput::forSubLotOf($lot);
        $input->title = $title;

        return $input;
    }

    private function user(): User
    {
        return new User()->setFirstName('Paula')->setLastName('Durand')->setEmail('paula@example.com');
    }
}
