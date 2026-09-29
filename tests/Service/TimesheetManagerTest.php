<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Lot;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Exception\TimeEntryRefusedException;
use App\Repository\TimeEntryRepository;
use App\Service\TimesheetManager;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TimesheetManagerTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    protected function setUp(): void
    {
        self::mockTime('2026-10-02 10:00');
    }

    public function testRecordCreatesUpdatesAndEmptiesACell(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject(), 5);

        $this->manager()->record($user, $lot, new \DateTimeImmutable('2026-09-28'), 2);
        self::assertSame([2], $this->quartersOf($user));

        $this->manager()->record($user, $lot, new \DateTimeImmutable('2026-09-28 17:00'), 4);
        self::assertSame([4], $this->quartersOf($user));

        $this->manager()->record($user, $lot, new \DateTimeImmutable('2026-09-28'), 0);
        self::assertSame([], $this->quartersOf($user));
    }

    public function testADayNeverExceedsAFullDay(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $this->createTimeEntry($user, $this->createLot($project), '2026-09-29', 3);
        $other = $this->createLot($project);

        try {
            $this->manager()->record($user, $other, new \DateTimeImmutable('2026-09-29'), 2);
            self::fail('A day above a full day is refused.');
        } catch (TimeEntryRefusedException $exception) {
            self::assertSame('Il ne reste que 0,25 j à saisir le 29/09.', $exception->getMessage());
        }

        $this->manager()->record($user, $other, new \DateTimeImmutable('2026-09-29'), 1);
        self::assertSame([3, 1], $this->quartersOf($user));
    }

    public function testAWeekNeverExceedsTheWeeklyMaximumInEffect(): void
    {
        $user = $this->createUser();
        $this->createWeeklyMax($user, '2026-09-28', 18);
        $lot = $this->createLot($this->createProject());
        foreach (['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01'] as $day) {
            $this->createTimeEntry($user, $lot, $day, 4);
        }

        try {
            $this->manager()->record($user, $lot, new \DateTimeImmutable('2026-10-02'), 3);
            self::fail('A week above its maximum is refused.');
        } catch (TimeEntryRefusedException $exception) {
            self::assertSame('Il ne reste que 0,5 j à saisir cette semaine (maximum 4,5 j).', $exception->getMessage());
        }

        $this->manager()->record($user, $lot, new \DateTimeImmutable('2026-10-02'), 2);
        self::assertSame(18, array_sum($this->quartersOf($user)));
    }

    public function testLoweringACellIsAcceptedOnAWeekAboveALoweredMaximum(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());
        foreach (['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02'] as $day) {
            $this->createTimeEntry($user, $lot, $day, 4);
        }
        $this->createWeeklyMax($user, '2026-09-28', 16);

        $this->manager()->record($user, $lot, new \DateTimeImmutable('2026-09-28'), 3);
        self::assertSame(19, array_sum($this->quartersOf($user)));

        $this->expectException(TimeEntryRefusedException::class);
        $this->manager()->record($user, $lot, new \DateTimeImmutable('2026-09-28'), 4);
    }

    #[DataProvider('refusedCellProvider')]
    public function testRefusedCells(string $day, int $quarters, bool $splitLot, string $message): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());
        if ($splitLot) {
            $this->createLot($lot->getProject(), parent: $lot);
        }

        try {
            $this->manager()->record($user, $lot, new \DateTimeImmutable($day), $quarters);
            self::fail('The cell is refused.');
        } catch (TimeEntryRefusedException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
        }
        self::assertSame([], $this->quartersOf($user));
    }

    /**
     * @return \Generator<array{string, int, bool, string}>
     */
    public static function refusedCellProvider(): \Generator
    {
        yield 'future day' => ['2026-10-05', 1, false, 'jour à venir'];
        yield 'week-end' => ['2026-09-27', 1, false, 'week-end'];
        yield 'split lot' => ['2026-09-28', 1, true, 'découpé en sous-lots'];
        yield 'more than a day' => ['2026-09-28', 5, false, '0, ¼, ½, ¾ ou 1 journée'];
        yield 'negative' => ['2026-09-28', -1, false, '0, ¼, ½, ¾ ou 1 journée'];
    }

    public function testFirstTimeEntryFreezesTheInitialEstimate(): void
    {
        $user = $this->createUser();
        $estimated = $this->createLot($this->createProject(), 5);
        $toEstimate = $this->createLot($estimated->getProject());

        $this->manager()->record($user, $estimated, new \DateTimeImmutable('2026-09-28'), 1);
        $this->manager()->record($user, $toEstimate, new \DateTimeImmutable('2026-09-28'), 1);
        $estimated->setEstimateDays(8);
        $this->manager()->record($user, $estimated, new \DateTimeImmutable('2026-09-29'), 1);

        self::assertSame(5, $this->reloadLot($estimated)->getInitialEstimateDays());
        self::assertNull($this->reloadLot($toEstimate)->getInitialEstimateDays());
    }

    private function manager(): TimesheetManager
    {
        $manager = self::getContainer()->get(TimesheetManager::class);
        \assert($manager instanceof TimesheetManager);

        return $manager;
    }

    /**
     * @return list<int>
     */
    private function quartersOf(User $user): array
    {
        $repository = self::getContainer()->get(TimeEntryRepository::class);
        \assert($repository instanceof TimeEntryRepository);
        $entries = $repository->findForUserBetween($user, new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-10-31'));
        usort($entries, static fn (TimeEntry $a, TimeEntry $b): int => $a->getId() <=> $b->getId());

        return array_map(static fn (TimeEntry $entry): int => $entry->getQuarters(), $entries);
    }

    private function reloadLot(Lot $lot): Lot
    {
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(Lot::class, $lot->getId());
        \assert($reloaded instanceof Lot);

        return $reloaded;
    }
}
