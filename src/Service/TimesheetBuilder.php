<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\User;
use App\Model\Quarters;
use App\Model\Timesheet\LeafOrder;
use App\Model\Timesheet\TimesheetCell;
use App\Model\Timesheet\TimesheetDay;
use App\Model\Timesheet\TimesheetRow;
use App\Model\Timesheet\WeekGrid;
use App\Model\Week;
use App\Repository\LotRepository;
use App\Repository\TimeEntryRepository;
use Psr\Clock\ClockInterface;

final readonly class TimesheetBuilder
{
    public function __construct(
        private TimeEntryRepository $timeEntryRepository,
        private LotRepository $lotRepository,
        private WeeklyMaxManager $weeklyMaxManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The rows are the leaves the user entered time on during this week or the previous one, plus the added leaves.
     *
     * @param list<int> $addedLotIds
     */
    public function build(User $user, Week $week, array $addedLotIds = []): WeekGrid
    {
        $lots = [];
        $quarters = [];
        foreach ($this->timeEntryRepository->findForUserBetween($user, $week->previous()->monday, $week->friday()) as $entry) {
            $lot = $entry->getLot();
            $lots[self::id($lot)] = $lot;
            if ($week->contains($entry->getDay())) {
                $quarters[self::id($lot)][$entry->getDay()->format('Y-m-d')] = $entry->getQuarters();
            }
        }

        $missingIds = array_values(array_diff($addedLotIds, array_keys($lots)));
        foreach ($this->lotRepository->findLeavesByIds($missingIds) as $lot) {
            $lots[self::id($lot)] = $lot;
        }
        $lots = array_values($lots);
        usort($lots, LeafOrder::compare(...));

        return $this->compose($week, $lots, $quarters, $this->weeklyMaxManager->quartersFor($user, $week));
    }

    /**
     * @param list<Lot>                            $lots
     * @param array<int, array<string, int<1, 4>>> $quarters quarters by lot id, then by day
     */
    private function compose(Week $week, array $lots, array $quarters, int $maxQuarters): WeekGrid
    {
        $today = $this->clock->now()->format('Y-m-d');
        $dayQuarters = [];
        foreach ($week->days() as $day) {
            $date = $day->format('Y-m-d');
            $dayQuarters[$date] = array_sum(array_map(static fn (array $byDay): int => $byDay[$date] ?? 0, $quarters));
        }
        $weekQuarters = array_sum($dayQuarters);

        $rows = array_map(
            fn (Lot $lot): TimesheetRow => new TimesheetRow($lot, array_map(
                fn (\DateTimeImmutable $day): TimesheetCell => $this->cell(
                    $day,
                    $quarters[self::id($lot)][$day->format('Y-m-d')] ?? 0,
                    $dayQuarters[$day->format('Y-m-d')],
                    $weekQuarters,
                    $maxQuarters,
                    $day->format('Y-m-d') > $today,
                ),
                $week->days(),
            )),
            $lots,
        );

        $days = array_map(
            static fn (\DateTimeImmutable $day): TimesheetDay => new TimesheetDay(
                $day,
                $dayQuarters[$day->format('Y-m-d')],
                $day->format('Y-m-d') === $today,
                $day->format('Y-m-d') < $today && $weekQuarters < $maxQuarters && $dayQuarters[$day->format('Y-m-d')] < Quarters::PER_DAY,
            ),
            $week->days(),
        );

        return new WeekGrid($week, $days, $rows, $weekQuarters, $maxQuarters);
    }

    /**
     * @param int<0, 4> $quarters
     */
    private function cell(\DateTimeImmutable $day, int $quarters, int $dayQuarters, int $weekQuarters, int $maxQuarters, bool $future): TimesheetCell
    {
        $allowed = min(Quarters::PER_DAY - ($dayQuarters - $quarters), $maxQuarters - ($weekQuarters - $quarters));

        return new TimesheetCell($day, $quarters, max($quarters, min(Quarters::PER_DAY, max(0, $allowed))), $future);
    }

    private static function id(Lot $lot): int
    {
        return $lot->getId() ?? throw new \LogicException('A leaf shown in a timesheet is persisted.');
    }
}
