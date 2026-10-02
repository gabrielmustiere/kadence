<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\User;
use App\Enum\Type\RoadmapSignal;
use App\Model\Person\PersonLeafRow;
use App\Model\Person\PersonLoad;
use App\Model\Person\PersonProject;
use App\Model\Person\PersonRoadmap;
use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\RoadmapBar;
use App\Model\Roadmap\RoadmapRun;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Roadmap\TimelineEntry;
use App\Model\Roadmap\TimelineMonth;
use App\Model\Schedule\LeafSchedule;
use App\Model\Schedule\ScheduleData;
use App\Model\Schedule\ScheduleResult;
use App\Model\Timesheet\LeafOrder;

final readonly class PersonRoadmapBuilder
{
    public function __construct(
        private ScheduleLoader $scheduleLoader,
        private Scheduler $scheduler,
        private RoadmapRunCutter $runCutter,
    ) {
    }

    /**
     * The page of a person, on a window spanning their runs and the future parts of the leaves of their teams.
     *
     * @param bool $withOverloads whether to flag the leaves loading someone beyond a full load: planning views only
     * @param bool $withLoad      whether to work out the load of the person
     */
    public function build(User $person, bool $withOverloads, bool $withLoad): PersonRoadmap
    {
        $data = $this->scheduleLoader->load();
        $result = $this->scheduler->schedule(array_values($data->plans), $data->capacity, $data->today);
        $userId = (int) $person->getId();
        $runs = $this->runCutter->cutFor($data, $userId);
        $shares = self::sharesOf($data, $userId);
        $leaves = self::leavesOf($data, array_keys($runs + $shares));

        $bounds = self::bounds($runs, $shares, $result);
        $window = null === $bounds ? RoadmapWindow::spanning($data->today, $data->today) : RoadmapWindow::spanning(...$bounds);
        $overloaded = $withOverloads ? $result->overloadedLots() : [];
        $rows = array_map(static fn (Lot $leaf): PersonLeafRow => self::row($leaf, $shares, $runs, $result, $window, $overloaded), $leaves);

        $upcoming = array_values(array_filter($rows, static fn (PersonLeafRow $row): bool => $row->isUpcoming($data->today)));
        usort($upcoming, static fn (PersonLeafRow $a, PersonLeafRow $b): int => [null === $a->start, $a->start] <=> [null === $b->start, $b->start]);
        $unknownEnds = array_values(array_filter($upcoming, static fn (PersonLeafRow $row): bool => null === $row->end));

        return new PersonRoadmap(
            new Roadmap($window, $data->today, []),
            self::projects($rows, $runs),
            $upcoming,
            TimelineMonth::group(TimelineEntry::ofLeaves($leaves, $runs)),
            null !== $bounds,
            $withLoad && $person->isActive() ? PersonLoad::of($result->loadsOf($userId), $data->capacity, $userId, $data->today, $window, $unknownEnds) : null,
        );
    }

    /**
     * @return array<int, int<25, 100>> the share of the person in the team of each leaf, by lot id
     */
    private static function sharesOf(ScheduleData $data, int $userId): array
    {
        $shares = [];
        foreach ($data->plans as $lotId => $plan) {
            foreach ($plan->members as $member) {
                if ($member->userId === $userId) {
                    $shares[$lotId] = $member->share;
                }
            }
        }

        return $shares;
    }

    /**
     * @param list<int> $lotIds
     *
     * @return array<int, Lot> in the order of the roadmap, by lot id
     */
    private static function leavesOf(ScheduleData $data, array $lotIds): array
    {
        $leaves = array_values(array_filter(array_map(static fn (int $lotId): ?Lot => $data->leaves[$lotId] ?? null, $lotIds)));
        usort($leaves, LeafOrder::compare(...));

        $byId = [];
        foreach ($leaves as $leaf) {
            $byId[(int) $leaf->getId()] = $leaf;
        }

        return $byId;
    }

    /**
     * The first and last days entered by the person or reached by the future parts of the leaves of their teams; null
     * when there is none.
     *
     * @param array<int, array{list<RoadmapRun>, list<RoadmapRun>}> $runs   by lot id
     * @param array<int, int>                                       $shares by lot id
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}|null
     */
    private static function bounds(array $runs, array $shares, ScheduleResult $result): ?array
    {
        $days = [];
        foreach ($runs as [$within, $beyond]) {
            foreach ([...$within, ...$beyond] as $run) {
                array_push($days, $run->from, $run->to);
            }
        }
        foreach (array_keys($shares) as $lotId) {
            $schedule = $result->get($lotId);
            if (null !== $schedule && $schedule->hasFuture()) {
                array_push($days, $schedule->futureFrom, $schedule->futureTo);
            }
        }
        $days = array_values(array_filter($days));

        return [] === $days ? null : [min($days), max($days)];
    }

    /**
     * @param array<int, int<25, 100>>                              $shares     by lot id
     * @param array<int, array{list<RoadmapRun>, list<RoadmapRun>}> $runs       by lot id
     * @param array<int, true>                                      $overloaded
     */
    private static function row(Lot $leaf, array $shares, array $runs, ScheduleResult $result, RoadmapWindow $window, array $overloaded): PersonLeafRow
    {
        $lotId = (int) $leaf->getId();
        $schedule = $result->get($lotId) ?? throw new \LogicException('Every leaf is scheduled.');
        $share = $shares[$lotId] ?? null;
        [$within, $beyond] = $runs[$lotId] ?? [[], []];
        $signals = RoadmapSignal::of($schedule);
        if (isset($overloaded[$lotId])) {
            $signals[] = RoadmapSignal::ToReplan;
        }

        return new PersonLeafRow(
            $leaf,
            $share,
            $schedule->hasFuture() ? $schedule->futureFrom : null,
            $schedule->end(),
            $window->segmentedBar($within),
            $window->segmentedBar($beyond),
            null !== $share ? self::future($schedule, $window) : null,
            $signals,
        );
    }

    private static function future(LeafSchedule $schedule, RoadmapWindow $window): ?RoadmapBar
    {
        return null === $schedule->futureFrom || null === $schedule->futureTo ? null : $window->bar($schedule->futureFrom, $schedule->futureTo);
    }

    /**
     * The rows grouped by project, with what the person entered on each.
     *
     * @param array<int, PersonLeafRow>                             $rows in the order of the roadmap, by lot id
     * @param array<int, array{list<RoadmapRun>, list<RoadmapRun>}> $runs by lot id
     *
     * @return list<PersonProject>
     */
    private static function projects(array $rows, array $runs): array
    {
        $grouped = [];
        foreach ($rows as $lotId => $row) {
            $grouped[(int) $row->lot->getProject()->getId()][$lotId] = $row;
        }

        $projects = [];
        foreach ($grouped as $projectRows) {
            $projectRuns = array_merge(...array_map(static fn (int $lotId): array => array_merge(...($runs[$lotId] ?? [[], []])), array_keys($projectRows)));
            $lastDays = array_map(static fn (RoadmapRun $run): \DateTimeImmutable => $run->to, $projectRuns);
            $first = reset($projectRows);
            $projects[] = new PersonProject(
                $first->lot->getProject(),
                array_values($projectRows),
                array_sum(array_map(static fn (RoadmapRun $run): int => $run->quarters, $projectRuns)),
                [] === $lastDays ? null : max($lastDays),
            );
        }

        return $projects;
    }
}
