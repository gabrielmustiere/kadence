<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Enum\Type\HolidayCalendar;
use App\Repository\HolidayAdjustmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The test database is not reset between tests: adjustments written by a test are dated 2031 or later, so that they
 * never reach the 2026 weeks of the timesheet tests, and are purged after each test.
 */
trait PurgesHolidayAdjustments
{
    abstract private function entityManager(): EntityManagerInterface;

    protected function tearDown(): void
    {
        $repository = static::getContainer()->get(HolidayAdjustmentRepository::class);
        \assert($repository instanceof HolidayAdjustmentRepository);
        $entityManager = $this->entityManager();
        foreach (HolidayCalendar::cases() as $calendar) {
            foreach ($repository->findBetween($calendar, new \DateTimeImmutable('2031-01-01'), new \DateTimeImmutable('2099-12-31')) as $adjustment) {
                $entityManager->remove($adjustment);
            }
        }
        $entityManager->flush();

        parent::tearDown();
    }
}
