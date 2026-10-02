<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Exception\LotProgressRefusedException;
use App\Repository\LotProgressRepository;
use App\Service\LotProgressManager;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LotProgressManagerTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    protected function setUp(): void
    {
        self::mockTime('2026-10-02 10:00');
    }

    public function testADeclarationAnchorsWhatIsLeftOnTheTimeEntered(): void
    {
        $author = $this->createUser();
        $lot = $this->leafWithTime(20, 10);

        $declaration = $this->manager()->declare($lot, 40, $author);

        self::assertSame('2026-10-02', $declaration->getDeclaredOn()->format('Y-m-d'));
        self::assertSame(40, $declaration->getPercent());
        self::assertSame(10 * 4, $declaration->getEnteredQuarters());
        self::assertSame(15 * 4, $declaration->getRemainingQuarters());
        self::assertSame($author, $declaration->getAuthor());
    }

    public function testWithoutTimeWhatIsLeftComesFromTheEstimate(): void
    {
        $declaration = $this->manager()->declare($this->createLot($this->createProject(), 10), 30, $this->createUser());

        self::assertSame(0, $declaration->getEnteredQuarters());
        self::assertSame(7 * 4, $declaration->getRemainingQuarters());
    }

    public function testASecondDeclarationTheSameDayReplacesTheFirst(): void
    {
        $lot = $this->leafWithTime(20, 10);
        $first = $this->manager()->declare($lot, 40, $this->createUser());
        $author = $this->createUser();

        $second = $this->manager()->declare($lot, 50, $author);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame(50, $second->getPercent());
        self::assertSame(10 * 4, $second->getRemainingQuarters());
        self::assertSame($author, $second->getAuthor());
        self::assertCount(1, $this->declarationsOf($lot));
    }

    public function testADeclarationAnotherDayIsKept(): void
    {
        $lot = $this->leafWithTime(20, 10);
        $this->manager()->declare($lot, 40, $this->createUser());

        self::mockTime('2026-10-05 09:00');
        $this->manager()->declare($lot, 50, $this->createUser());

        self::assertCount(2, $this->declarationsOf($lot));
    }

    public function testZeroPercentWithdrawsTheProgress(): void
    {
        $declaration = $this->manager()->declare($this->leafWithTime(20, 10), 0, $this->createUser());

        self::assertSame(0, $declaration->getPercent());
        self::assertNull($declaration->getRemainingQuarters());
    }

    public function testALeafWithoutTimeCannotBeCompleteAtAHundredPercent(): void
    {
        $lot = $this->createLot($this->createProject(), 10);

        try {
            $this->manager()->declare($lot, 100, $this->createUser());
            self::fail('A leaf without time cannot be declared complete.');
        } catch (LotProgressRefusedException $exception) {
            self::assertSame(\sprintf('Aucun temps n\'est saisi sur « %s » : son avancement ne peut pas être de 100 %%.', $lot->getTitle()), $exception->getMessage());
        }

        self::assertSame([], $this->declarationsOf($lot));
    }

    public function testAPercentOffTheStepsIsRefused(): void
    {
        $lot = $this->leafWithTime(20, 10);

        $this->expectException(LotProgressRefusedException::class);

        $this->manager()->declare($lot, 37, $this->createUser());
    }

    public function testASplitLotOrALeafToEstimateHasNoProgress(): void
    {
        $project = $this->createProject();
        $split = $this->createLot($project);
        $this->createLot($project, 3, parent: $split);

        $this->expectException(\LogicException::class);

        $this->manager()->declare($split, 40, $this->createUser());
    }

    /**
     * @param positive-int $estimateDays
     * @param positive-int $enteredDays
     */
    private function leafWithTime(int $estimateDays, int $enteredDays): Lot
    {
        $lot = $this->createLot($this->createProject(), $estimateDays);
        $user = $this->createUser();
        $day = new \DateTimeImmutable('2026-09-01');
        for ($i = 0; $i < $enteredDays; ++$i, $day = $day->modify('+1 weekday')) {
            $this->createTimeEntry($user, $lot, $day->format('Y-m-d'), 4);
        }

        return $lot;
    }

    /**
     * @return list<LotProgress>
     */
    private function declarationsOf(Lot $lot): array
    {
        $repository = self::getContainer()->get(LotProgressRepository::class);
        \assert($repository instanceof LotProgressRepository);

        return $repository->findBy(['lot' => $lot]);
    }

    private function manager(): LotProgressManager
    {
        $manager = self::getContainer()->get(LotProgressManager::class);
        \assert($manager instanceof LotProgressManager);

        return $manager;
    }
}
