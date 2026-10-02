<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\Project;
use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\ProjectRoadmap;
use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\RoadmapBar;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapRun;
use App\Model\Roadmap\RoadmapTeamLine;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Roadmap\TimelineEntry;
use App\Model\Roadmap\TimelineMonth;
use App\Model\Schedule\LeafPlan;
use App\Model\Schedule\LeafSchedule;
use App\Model\Schedule\ScheduleData;
use App\Model\Schedule\ScheduleResult;
use App\Repository\ProjectRepository;
use App\Repository\TimeEntryRepository;

final readonly class RoadmapBuilder
{
    public function __construct(
        private ScheduleLoader $scheduleLoader,
        private Scheduler $scheduler,
        private ProjectRepository $projectRepository,
        private TimeEntryRepository $timeEntryRepository,
        private RoadmapRunCutter $runCutter,
    ) {
    }

    /**
     * @param bool $withOverloads whether to flag the leaves loading someone beyond a full load: planning views only
     */
    public function build(RoadmapWindow $window, bool $withOverloads): Roadmap
    {
        $data = $this->scheduleLoader->load();
        $result = $this->scheduler->schedule(array_values($data->plans), $data->capacity, $data->today);
        $overloaded = $withOverloads ? $result->overloadedLots() : [];
        $recaps = $this->recaps($data);
        $entered = array_keys(array_filter($data->plans, static fn (LeafPlan $plan): bool => null !== $plan->firstEntryDay && true === $result->get($plan->lotId)?->isPlanned()));
        $parts = self::enteredParts($this->runCutter->cut($data, $entered), $result, $window);

        $projects = array_map(fn (Project $project): RoadmapRow => $this->projectRow($project, $window, $result, $overloaded, $parts, $recaps), $this->projectRepository->findAllForList());

        return new Roadmap($window, $data->today, $projects);
    }

    /**
     * The roadmap of a single project, on a window spanning its days, with the timeline of every run entered on its
     * leaves, planned or not.
     *
     * @param bool $withOverloads whether to flag the leaves loading someone beyond a full load: planning views only
     */
    public function buildProject(Project $project, bool $withOverloads): ProjectRoadmap
    {
        $data = $this->scheduleLoader->load();
        $result = $this->scheduler->schedule(array_values($data->plans), $data->capacity, $data->today);
        $overloaded = $withOverloads ? $result->overloadedLots() : [];
        $leaves = self::leavesOf($project);
        $runs = $this->runCutter->cut($data, array_keys(array_filter($leaves, static fn (Lot $leaf): bool => null !== ($data->plans[(int) $leaf->getId()]->firstEntryDay ?? null))));

        $bounds = self::bounds($leaves, $result);
        $window = null === $bounds ? RoadmapWindow::spanning($data->today, $data->today) : RoadmapWindow::spanning(...$bounds);
        $row = $this->projectRow($project, $window, $result, $overloaded, self::enteredParts($runs, $result, $window), $this->recaps($data));

        return new ProjectRoadmap(new Roadmap($window, $data->today, [$row]), $row, TimelineMonth::group(TimelineEntry::ofLeaves($leaves, $runs)), null !== $bounds);
    }

    /**
     * @return array<int, Lot> the leaves of the project in the order of its lots, by lot id
     */
    private static function leavesOf(Project $project): array
    {
        $leaves = [];
        foreach ($project->getLots() as $lot) {
            if ($lot->isSubLot()) {
                continue;
            }
            foreach ($lot->isLeaf() ? [$lot] : $lot->getChildren() as $leaf) {
                $leaves[(int) $leaf->getId()] = $leaf;
            }
        }

        return $leaves;
    }

    /**
     * The first and last days reached by the planned leaves or entered on any of them; null when there is none.
     *
     * @param array<int, Lot> $leaves by lot id
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}|null
     */
    private static function bounds(array $leaves, ScheduleResult $result): ?array
    {
        $days = [];
        foreach ($leaves as $lotId => $leaf) {
            $schedule = $result->get($lotId);
            if (null === $schedule) {
                continue;
            }
            if ($schedule->isPlanned()) {
                array_push($days, ...self::reach($leaf, $schedule));
            }
            array_push($days, $schedule->realizedFrom, $schedule->realizedTo);
        }
        $days = array_values(array_filter($days));

        return [] === $days ? null : [min($days), max($days)];
    }

    /**
     * @param array<int, true>                                    $overloaded
     * @param array<int, array{RoadmapBar|null, RoadmapBar|null}> $parts
     * @param array<int, array{list<RoadmapTeamLine>, int}>       $recaps
     */
    private function projectRow(Project $project, RoadmapWindow $window, ScheduleResult $result, array $overloaded, array $parts, array $recaps): RoadmapRow
    {
        $lots = [];
        foreach ($project->getLots() as $lot) {
            if (!$lot->isSubLot()) {
                $lots[] = $lot->isLeaf() ? $this->leafRow($lot, $window, $result, $overloaded, $parts, $recaps) : $this->splitLotRow($lot, $window, $result, $overloaded, $parts, $recaps);
            }
        }

        $leaves = array_merge(...array_map(static fn (RoadmapRow $row): array => $row->isLeaf() ? [$row] : $row->children, $lots));
        $signals = match (true) {
            [] === $lots => [RoadmapSignal::Unsplit],
            array_any($leaves, static fn (RoadmapRow $leaf): bool => null === $leaf->start) => [RoadmapSignal::PartialPlanning],
            default => [],
        };

        return $this->spanRow($project, null, $lots, $window, $signals);
    }

    /**
     * @param array<int, true>                                    $overloaded
     * @param array<int, array{RoadmapBar|null, RoadmapBar|null}> $parts
     * @param array<int, array{list<RoadmapTeamLine>, int}>       $recaps
     */
    private function splitLotRow(Lot $lot, RoadmapWindow $window, ScheduleResult $result, array $overloaded, array $parts, array $recaps): RoadmapRow
    {
        $children = array_map(fn (Lot $child): RoadmapRow => $this->leafRow($child, $window, $result, $overloaded, $parts, $recaps), $lot->getChildren()->getValues());

        return $this->spanRow($lot->getProject(), $lot, $children, $window, []);
    }

    /**
     * A lot or a project reaches from the earliest start of its leaves to the latest day they reach; its end is unknown
     * as soon as one of its planned leaves has none.
     *
     * @param list<RoadmapRow>    $children
     * @param list<RoadmapSignal> $signals
     */
    private function spanRow(Project $project, ?Lot $lot, array $children, RoadmapWindow $window, array $signals): RoadmapRow
    {
        $planned = array_values(array_filter($children, static fn (RoadmapRow $row): bool => null !== $row->start));
        $starts = array_filter(array_map(static fn (RoadmapRow $row): ?\DateTimeImmutable => $row->start, $planned));
        $lastDays = array_filter(array_map(static fn (RoadmapRow $row): ?\DateTimeImmutable => $row->lastDay, $planned));
        $ends = array_filter(array_map(static fn (RoadmapRow $row): ?\DateTimeImmutable => $row->end, $planned));
        $start = [] === $starts ? null : min($starts);
        $lastDay = [] === $lastDays ? null : max($lastDays);
        $endKnown = [] !== $planned && !array_any($planned, static fn (RoadmapRow $row): bool => $row->isEndUnknown());

        return new RoadmapRow(
            $project,
            $lot,
            start: $start,
            end: $endKnown && [] !== $ends ? max($ends) : null,
            lastDay: $lastDay,
            span: null === $start || null === $lastDay ? null : $window->bar($start, $lastDay),
            signals: $signals,
            children: $children,
        );
    }

    /**
     * @param array<int, true>                                    $overloaded
     * @param array<int, array{RoadmapBar|null, RoadmapBar|null}> $parts
     * @param array<int, array{list<RoadmapTeamLine>, int}>       $recaps
     */
    private function leafRow(Lot $leaf, RoadmapWindow $window, ScheduleResult $result, array $overloaded, array $parts, array $recaps): RoadmapRow
    {
        $lotId = (int) $leaf->getId();
        $schedule = $result->get($lotId);
        if (null === $schedule) {
            return new RoadmapRow($leaf->getProject(), $leaf);
        }

        $signals = RoadmapSignal::of($schedule);
        if (isset($overloaded[$lotId])) {
            $signals[] = RoadmapSignal::ToReplan;
        }

        $members = $leaf->getMembers()->getValues();
        [$team, $enteredDayCount] = $recaps[$lotId] ?? [[], 0];
        if (!$schedule->isPlanned()) {
            return new RoadmapRow(
                $leaf->getProject(),
                $leaf,
                remainingQuarters: $schedule->remainingQuarters,
                members: $members,
                signals: $signals,
                enteredFrom: $schedule->realizedFrom,
                enteredTo: $schedule->realizedTo,
                enteredDayCount: $enteredDayCount,
                team: $team,
            );
        }

        [$start, $lastDay] = self::reach($leaf, $schedule);
        [$realized, $overrun] = $parts[$lotId] ?? [null, null];

        return new RoadmapRow(
            $leaf->getProject(),
            $leaf,
            start: $start,
            end: $schedule->end(),
            lastDay: $lastDay,
            span: null === $start || null === $lastDay ? null : $window->bar($start, $lastDay),
            realized: $realized,
            overrun: $overrun,
            future: null === $schedule->futureFrom || null === $schedule->futureTo ? null : $window->bar($schedule->futureFrom, $schedule->futureTo),
            remainingQuarters: $schedule->remainingQuarters,
            members: $members,
            signals: $signals,
            enteredFrom: $schedule->realizedFrom,
            enteredTo: $schedule->realizedTo,
            enteredDayCount: $enteredDayCount,
            team: $team,
        );
    }

    /**
     * The first day of a planned leaf and the last day it reaches.
     *
     * @return array{\DateTimeImmutable|null, \DateTimeImmutable|null}
     */
    private static function reach(Lot $leaf, LeafSchedule $schedule): array
    {
        return [$schedule->start() ?? $leaf->getStartDate(), $schedule->end() ?? $schedule->realizedTo];
    }

    /**
     * What each person entered on every leaf with time entered, and its number of days entered.
     *
     * @return array<int, array{list<RoadmapTeamLine>, int}> by lot id
     */
    private function recaps(ScheduleData $data): array
    {
        $recaps = [];
        foreach ($this->timeEntryRepository->sumQuartersByLotAndUser() as $lotId => $entered) {
            $leaf = $data->leaves[$lotId] ?? null;
            if (null !== $leaf) {
                $recaps[$lotId] = [RoadmapTeamLine::forLeaf($leaf, $entered, $data->people), $data->plans[$lotId]->enteredDayCount ?? 0];
            }
        }

        return $recaps;
    }

    /**
     * The runs of the planned leaves placed on the window.
     *
     * @param array<int, array{list<RoadmapRun>, list<RoadmapRun>}> $runs the runs within and beyond the estimate, by lot id
     *
     * @return array<int, array{RoadmapBar|null, RoadmapBar|null}> the bars within and beyond the estimate, by lot id
     */
    private static function enteredParts(array $runs, ScheduleResult $result, RoadmapWindow $window): array
    {
        $parts = [];
        foreach ($runs as $lotId => [$within, $beyond]) {
            if (true === $result->get($lotId)?->isPlanned()) {
                $parts[$lotId] = [$window->segmentedBar($within), $window->segmentedBar($beyond)];
            }
        }

        return $parts;
    }
}
