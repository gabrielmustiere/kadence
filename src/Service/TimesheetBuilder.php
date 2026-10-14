<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\User;
use App\Model\LeafOrder;
use App\Model\Quarters;
use App\Model\TimeOff\TimeOffDay;
use App\Model\Timesheet\TimesheetCell;
use App\Model\Timesheet\TimesheetDay;
use App\Model\Timesheet\TimesheetRow;
use App\Model\Timesheet\WeekGrid;
use App\Model\Week;
use App\Repository\FavoriteLotRepository;
use App\Repository\LotRepository;
use App\Repository\TimeEntryRepository;
use Psr\Clock\ClockInterface;

final readonly class TimesheetBuilder
{
    public function __construct(
        private TimeEntryRepository $timeEntryRepository,
        private LotRepository $lotRepository,
        private FavoriteLotRepository $favoriteLotRepository,
        private WeeklyMaxManager $weeklyMaxManager,
        private HolidayManager $holidayManager,
        private TimeOffBook $timeOffBook,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The rows are the user's favorite leaves, then the leaves they entered time on during this week or the previous
     * one and the added leaves, each group in tree order.
     *
     * @param list<int> $addedLotIds
     */
    public function build(User $user, Week $week, array $addedLotIds = []): WeekGrid
    {
        $favorites = [];
        foreach ($this->favoriteLotRepository->findLotsOf($user) as $lot) {
            $favorites[self::id($lot)] = $lot;
        }

        $lots = [];
        $quarters = [];
        foreach ($this->timeEntryRepository->findForUserBetween($user, $week->previous()->monday, $week->friday()) as $entry) {
            $lot = $entry->getLot();
            $lots[self::id($lot)] = $lot;
            if ($week->contains($entry->getDay())) {
                $quarters[self::id($lot)][$entry->getDay()->format('Y-m-d')] = $entry->getQuarters();
            }
        }

        $missingIds = array_values(array_diff($addedLotIds, array_keys($lots + $favorites)));
        foreach ($this->lotRepository->findLeavesByIds($missingIds) as $lot) {
            $lots[self::id($lot)] = $lot;
        }
        $others = array_values(array_diff_key($lots, $favorites));
        usort($others, LeafOrder::compare(...));
        $favoriteLots = array_values($favorites);
        usort($favoriteLots, LeafOrder::compare(...));

        [$days, $maxQuarters] = $this->days($user, $week, $quarters);

        return $this->compose($week, [...$favoriteLots, ...$others], array_keys($favorites), $quarters, $days, $maxQuarters);
    }

    /**
     * The days of the week as Ma semaine shows them, without its rows: what each takes, what was entered, which are
     * forgotten.
     *
     * @return list<TimesheetDay>
     */
    public function daysOf(User $user, Week $week): array
    {
        $quarters = [];
        foreach ($this->timeEntryRepository->findForUserBetween($user, $week->monday, $week->friday()) as $entry) {
            $quarters[self::id($entry->getLot())][$entry->getDay()->format('Y-m-d')] = $entry->getQuarters();
        }

        return $this->days($user, $week, $quarters)[0];
    }

    /**
     * @param array<int, array<string, int<1, 4>>> $quarters quarters by lot id, then by day
     *
     * @return array{list<TimesheetDay>, int} the days, and the quarters the week takes at most
     */
    private function days(User $user, Week $week, array $quarters): array
    {
        $holidays = $this->holidayManager->holidaysOf($user, $week);
        $timeOff = $this->timeOffBook->daysOf($user, $week->monday, $week->friday(), $holidays);
        $maxQuarters = $this->weeklyMaxManager->capFor($user, $week, \count($holidays), TimeOffDay::halfDayCountOf($timeOff));

        $today = $this->clock->now()->format('Y-m-d');
        $dayQuarters = [];
        $dayCaps = [];
        foreach ($week->days() as $day) {
            $date = $day->format('Y-m-d');
            $dayQuarters[$date] = array_sum(array_map(static fn (array $byDay): int => $byDay[$date] ?? 0, $quarters));
            $dayCaps[$date] = isset($holidays[$date]) ? 0 : ($timeOff[$date] ?? null)?->capQuarters() ?? Quarters::PER_DAY;
        }
        $weekQuarters = array_sum($dayQuarters);

        $days = array_map(
            static fn (\DateTimeImmutable $day): TimesheetDay => new TimesheetDay(
                $day,
                $dayQuarters[$day->format('Y-m-d')],
                $day->format('Y-m-d') === $today,
                $day->format('Y-m-d') < $today && $weekQuarters < $maxQuarters && $dayQuarters[$day->format('Y-m-d')] < $dayCaps[$day->format('Y-m-d')],
                $holidays[$day->format('Y-m-d')] ?? null,
                $dayCaps[$day->format('Y-m-d')],
                $timeOff[$day->format('Y-m-d')] ?? null,
            ),
            $week->days(),
        );

        return [$days, $maxQuarters];
    }

    /**
     * @param list<Lot>                            $lots
     * @param list<int>                            $favoriteIds
     * @param array<int, array<string, int<1, 4>>> $quarters    quarters by lot id, then by day
     * @param list<TimesheetDay>                   $days
     */
    private function compose(Week $week, array $lots, array $favoriteIds, array $quarters, array $days, int $maxQuarters): WeekGrid
    {
        $today = $this->clock->now()->format('Y-m-d');
        $weekQuarters = array_sum(array_map(static fn (TimesheetDay $day): int => $day->quarters, $days));

        $rows = array_map(
            fn (Lot $lot): TimesheetRow => new TimesheetRow($lot, array_map(
                fn (TimesheetDay $day): TimesheetCell => $this->cell(
                    $day->date,
                    $quarters[self::id($lot)][$day->date->format('Y-m-d')] ?? 0,
                    $day->quarters,
                    $day->capQuarters,
                    $weekQuarters,
                    $maxQuarters,
                    $day->date->format('Y-m-d') > $today || 0 === $day->capQuarters,
                ),
                $days,
            ), \in_array(self::id($lot), $favoriteIds, true)),
            $lots,
        );

        return new WeekGrid($week, $days, $rows, $weekQuarters, $maxQuarters);
    }

    /**
     * @param int<0, 4> $quarters
     * @param int<0, 4> $dayCap
     */
    private function cell(\DateTimeImmutable $day, int $quarters, int $dayQuarters, int $dayCap, int $weekQuarters, int $maxQuarters, bool $locked): TimesheetCell
    {
        $allowed = min($dayCap - ($dayQuarters - $quarters), $maxQuarters - ($weekQuarters - $quarters));

        return new TimesheetCell($day, $quarters, max($quarters, min(Quarters::PER_DAY, max(0, $allowed))), $locked);
    }

    private static function id(Lot $lot): int
    {
        return $lot->getId() ?? throw new \LogicException('A leaf shown in a timesheet is persisted.');
    }
}
