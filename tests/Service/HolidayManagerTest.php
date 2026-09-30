<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\Type\HolidayCalendar;
use App\Exception\HolidayAdjustmentRefusedException;
use App\Model\Holiday\HolidayLine;
use App\Model\Week;
use App\Service\HolidayManager;
use App\Tests\Support\CreatesUsers;
use App\Tests\Support\PurgesHolidayAdjustments;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class HolidayManagerTest extends KernelTestCase
{
    use CreatesUsers;
    use PurgesHolidayAdjustments;

    public function testHolidaysOfFollowTheCalendarOfThePerson(): void
    {
        $french = $this->createUser();
        $belgian = $this->createUser(holidayCalendar: HolidayCalendar::Belgium);

        self::assertSame(['2026-07-14' => 'Fête nationale'], $this->manager()->holidaysOf($french, Week::fromIso('2026-W29')));
        self::assertSame([], $this->manager()->holidaysOf($belgian, Week::fromIso('2026-W29')));
        self::assertSame([], $this->manager()->holidaysOf($french, Week::fromIso('2026-W30')));
        self::assertSame(['2026-07-21' => 'Fête nationale'], $this->manager()->holidaysOf($belgian, Week::fromIso('2026-W30')));
    }

    public function testHolidaysOfAWeekAcrossTwoYears(): void
    {
        self::assertSame(['2027-01-01' => 'Jour de l\'an'], $this->manager()->holidaysOf($this->createUser(), Week::fromIso('2026-W53')));
    }

    public function testAnAddedHolidayAppliesToItsCalendarOnlyUntilCancelled(): void
    {
        $french = $this->createUser();
        $belgian = $this->createUser(holidayCalendar: HolidayCalendar::Belgium);
        $week = Week::fromIso('2031-W24');

        $adjustment = $this->manager()->add(HolidayCalendar::Belgium, new \DateTimeImmutable('2031-06-11'), 'Remplacement du 15 août');

        self::assertSame(['2031-06-11' => 'Remplacement du 15 août'], $this->manager()->holidaysOf($belgian, $week));
        self::assertSame([], $this->manager()->holidaysOf($french, $week));

        $this->manager()->cancel($adjustment);

        self::assertSame([], $this->manager()->holidaysOf($belgian, $week));
    }

    public function testARemovedLegalHolidayIsWorkedOnThatDateOnlyUntilCancelled(): void
    {
        $belgian = $this->createUser(holidayCalendar: HolidayCalendar::Belgium);

        $adjustment = $this->manager()->remove(HolidayCalendar::Belgium, new \DateTimeImmutable('2031-06-02'));

        self::assertSame([], $this->manager()->holidaysOf($belgian, Week::fromIso('2031-W23')));
        self::assertTrue($this->manager()->isHoliday(HolidayCalendar::France, new \DateTimeImmutable('2031-06-02')));
        self::assertTrue($this->manager()->isHoliday(HolidayCalendar::Belgium, new \DateTimeImmutable('2032-05-17')));

        $this->manager()->cancel($adjustment);

        self::assertSame(['2031-06-02' => 'Lundi de Pentecôte'], $this->manager()->holidaysOf($belgian, Week::fromIso('2031-W23')));
    }

    public function testRemovingADayThatIsNotALegalHolidayIsRefused(): void
    {
        $this->expectException(HolidayAdjustmentRefusedException::class);
        $this->expectExceptionMessage('Le 03/06/2031 n\'est pas un jour férié légal du calendrier France');

        $this->manager()->remove(HolidayCalendar::France, new \DateTimeImmutable('2031-06-03'));
    }

    public function testRemovingAnAlreadyRemovedDayIsRefused(): void
    {
        $this->manager()->remove(HolidayCalendar::France, new \DateTimeImmutable('2031-07-14'));

        $this->expectException(HolidayAdjustmentRefusedException::class);
        $this->expectExceptionMessage('Le 14/07/2031 est déjà retiré du calendrier France.');

        $this->manager()->remove(HolidayCalendar::France, new \DateTimeImmutable('2031-07-14'));
    }

    public function testYearOfListsLegalHolidaysWithWeekendsAndAdjustments(): void
    {
        $this->manager()->add(HolidayCalendar::Belgium, new \DateTimeImmutable('2031-06-11'), 'Remplacement du 15 août');
        $this->manager()->remove(HolidayCalendar::Belgium, new \DateTimeImmutable('2031-06-02'));

        $lines = $this->manager()->yearOf(HolidayCalendar::Belgium, 2031);

        self::assertCount(11, $lines);
        $byDay = array_combine(array_map(static fn (HolidayLine $line): string => $line->day->format('Y-m-d'), $lines), $lines);
        self::assertTrue($byDay['2031-06-02']->isRemoved());
        self::assertSame('Lundi de Pentecôte', $byDay['2031-06-02']->label);
        self::assertTrue($byDay['2031-06-11']->isAdded());
        self::assertSame('Remplacement du 15 août', $byDay['2031-06-11']->label);
        self::assertFalse($byDay['2031-07-21']->isAdded() || $byDay['2031-07-21']->isRemoved());

        $french2026 = $this->manager()->yearOf(HolidayCalendar::France, 2026);
        self::assertCount(11, $french2026);
        self::assertSame(
            ['2026-08-15', '2026-11-01'],
            array_values(array_map(static fn (HolidayLine $line): string => $line->day->format('Y-m-d'), array_filter($french2026, static fn (HolidayLine $line): bool => $line->isWeekend()))),
        );
    }

    private function manager(): HolidayManager
    {
        $manager = self::getContainer()->get(HolidayManager::class);
        \assert($manager instanceof HolidayManager);

        return $manager;
    }
}
