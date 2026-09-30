<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\HolidayAdjustment;
use App\Entity\User;
use App\Enum\Type\HolidayCalendar;
use App\Exception\HolidayAdjustmentRefusedException;
use App\Model\Holiday\HolidayLine;
use App\Model\Week;
use App\Repository\HolidayAdjustmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The holidays of a calendar are its legal holidays, plus the days added, minus the legal days removed. Only the
 * adjustments are stored.
 */
final readonly class HolidayManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HolidayAdjustmentRepository $holidayAdjustmentRepository,
        private LegalHolidays $legalHolidays,
    ) {
    }

    /**
     * @return array<string, non-empty-string> labels by day (Y-m-d) of the user's holidays, Monday to Friday
     */
    public function holidaysOf(User $user, Week $week): array
    {
        return $this->holidaysBetween($user->getHolidayCalendar(), $week->monday, $week->friday());
    }

    public function isHoliday(HolidayCalendar $calendar, \DateTimeImmutable $day): bool
    {
        return [] !== $this->holidaysBetween($calendar, $day, $day);
    }

    public function adjustmentAt(HolidayCalendar $calendar, \DateTimeImmutable $day): ?HolidayAdjustment
    {
        return $this->holidayAdjustmentRepository->findOneAt($calendar, $day->setTime(0, 0));
    }

    /**
     * @return list<HolidayLine> in date order, weekends included; a removal on a day that is not a legal holiday is left out
     */
    public function yearOf(HolidayCalendar $calendar, int $year): array
    {
        $adjustments = [];
        foreach ($this->holidayAdjustmentRepository->findBetween($calendar, new \DateTimeImmutable(\sprintf('%d-01-01', $year)), new \DateTimeImmutable(\sprintf('%d-12-31', $year))) as $adjustment) {
            $adjustments[$adjustment->getDay()->format('Y-m-d')] = $adjustment;
        }

        $lines = [];
        foreach ($this->legalHolidays->forYear($calendar, $year) as $day => $label) {
            $lines[$day] = new HolidayLine(new \DateTimeImmutable($day), $label, $adjustments[$day] ?? null);
        }
        foreach ($adjustments as $day => $adjustment) {
            if ($adjustment->isAdded()) {
                $lines[$day] = new HolidayLine($adjustment->getDay(), (string) $adjustment->getLabel(), $adjustment);
            }
        }
        ksort($lines);

        return array_values($lines);
    }

    /**
     * @param non-empty-string $label
     */
    public function add(HolidayCalendar $calendar, \DateTimeImmutable $day, string $label): HolidayAdjustment
    {
        $adjustment = HolidayAdjustment::added($calendar, $day, $label);
        $this->entityManager->persist($adjustment);
        $this->entityManager->flush();

        return $adjustment;
    }

    /**
     * @throws HolidayAdjustmentRefusedException
     */
    public function remove(HolidayCalendar $calendar, \DateTimeImmutable $day): HolidayAdjustment
    {
        $day = $day->setTime(0, 0);
        if (!isset($this->legalHolidays->forYear($calendar, (int) $day->format('Y'))[$day->format('Y-m-d')])) {
            throw HolidayAdjustmentRefusedException::notALegalHoliday($calendar, $day);
        }
        if (null !== $this->adjustmentAt($calendar, $day)) {
            throw HolidayAdjustmentRefusedException::alreadyRemoved($calendar, $day);
        }

        $adjustment = HolidayAdjustment::removed($calendar, $day);
        $this->entityManager->persist($adjustment);
        $this->entityManager->flush();

        return $adjustment;
    }

    public function cancel(HolidayAdjustment $adjustment): void
    {
        $this->entityManager->remove($adjustment);
        $this->entityManager->flush();
    }

    /**
     * @return array<string, non-empty-string> labels by day (Y-m-d) between two days included, in date order
     */
    public function holidaysBetween(HolidayCalendar $calendar, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $first = $from->format('Y-m-d');
        $last = $to->format('Y-m-d');

        $holidays = [];
        for ($year = (int) $from->format('Y'); $year <= (int) $to->format('Y'); ++$year) {
            $holidays += $this->legalHolidays->forYear($calendar, $year);
        }
        $holidays = array_filter($holidays, static fn (string $day): bool => $day >= $first && $day <= $last, \ARRAY_FILTER_USE_KEY);

        foreach ($this->holidayAdjustmentRepository->findBetween($calendar, $from->setTime(0, 0), $to->setTime(0, 0)) as $adjustment) {
            $day = $adjustment->getDay()->format('Y-m-d');
            $label = $adjustment->getLabel();
            if (null !== $label && $adjustment->isAdded()) {
                $holidays[$day] = $label;
            } else {
                unset($holidays[$day]);
            }
        }
        ksort($holidays);

        return $holidays;
    }
}
