<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Model\Week;
use App\Repository\WeeklyMaxRepository;
use App\Service\WeeklyMaxManager;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WeeklyMaxManagerTest extends KernelTestCase
{
    use CreatesTimeEntries;
    use CreatesUsers;

    public function testFullTimeWithoutAnyValue(): void
    {
        self::assertSame(20, $this->manager()->quartersFor($this->createUser(), Week::fromIso('2026-W40')));
    }

    public function testEachWeekUsesTheValueInEffectOnItsMonday(): void
    {
        $user = $this->createUser();
        $this->createWeeklyMax($user, '2026-09-07', 18);
        $this->createWeeklyMax($user, '2026-10-05', 16);

        self::assertSame(20, $this->manager()->quartersFor($user, Week::fromIso('2026-W36')));
        self::assertSame(18, $this->manager()->quartersFor($user, Week::fromIso('2026-W37')));
        self::assertSame(18, $this->manager()->quartersFor($user, Week::fromIso('2026-W40')));
        self::assertSame(16, $this->manager()->quartersFor($user, Week::fromIso('2026-W41')));
    }

    public function testChangeWritesOnlyWhenTheValueDiffersAndReplacesTheSameWeek(): void
    {
        $user = $this->createUser();
        $manager = $this->manager();

        $manager->change($user, 20, Week::fromIso('2026-W40'));
        $this->entityManager()->flush();
        self::assertSame([], $this->repository()->findForUser($user));

        $manager->change($user, 18, Week::fromIso('2026-W40'));
        $this->entityManager()->flush();
        $manager->change($user, 17, Week::fromIso('2026-W40'));
        $this->entityManager()->flush();

        $history = $this->repository()->findForUser($user);
        self::assertCount(1, $history);
        self::assertSame(17, $history[0]->getQuarters());
        self::assertSame('2026-09-28', $history[0]->getEffectiveFrom()->format('Y-m-d'));
    }

    public function testDeletingAValueRestoresThePreviousOne(): void
    {
        $user = $this->createUser();
        $this->createWeeklyMax($user, '2026-09-07', 18);
        $later = $this->createWeeklyMax($user, '2026-10-05', 16);

        $this->manager()->delete($later);

        self::assertSame(18, $this->manager()->quartersFor($user, Week::fromIso('2026-W41')));
    }

    public function testTheCapOfAWeekLeavesItsHolidaysOut(): void
    {
        $fullTime = $this->createUser();
        $ninetyPercent = $this->createUser();
        $sixtyPercent = $this->createUser();
        $this->createWeeklyMax($ninetyPercent, '2026-09-28', 18);
        $this->createWeeklyMax($sixtyPercent, '2026-09-28', 12);
        $week = Week::fromIso('2026-W40');

        self::assertSame(20, $this->manager()->capFor($fullTime, $week, 0));
        self::assertSame(16, $this->manager()->capFor($fullTime, $week, 1));
        self::assertSame(0, $this->manager()->capFor($fullTime, $week, 5));
        self::assertSame(16, $this->manager()->capFor($ninetyPercent, $week, 1));
        self::assertSame(12, $this->manager()->capFor($sixtyPercent, $week, 2));
        self::assertSame(8, $this->manager()->capFor($sixtyPercent, $week, 3));
    }

    private function manager(): WeeklyMaxManager
    {
        $manager = self::getContainer()->get(WeeklyMaxManager::class);
        \assert($manager instanceof WeeklyMaxManager);

        return $manager;
    }

    private function repository(): WeeklyMaxRepository
    {
        $repository = self::getContainer()->get(WeeklyMaxRepository::class);
        \assert($repository instanceof WeeklyMaxRepository);

        return $repository;
    }
}
