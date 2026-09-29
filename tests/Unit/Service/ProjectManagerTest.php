<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\LotInput;
use App\Dto\ProjectInput;
use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\LotDepthException;
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

        $project = new ProjectManager($entityManager)->createProject($input);

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

        new ProjectManager($entityManager)->addSubLot($subLot, $this->lotInput('Trop profond', 1, null));
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

    private function manager(): ProjectManager
    {
        return new ProjectManager($this->createStub(EntityManagerInterface::class));
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
