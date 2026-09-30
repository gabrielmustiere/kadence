<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\Project;
use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\RoadmapBar;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Schedule\LeafSchedule;
use App\Model\Schedule\ScheduleResult;
use App\Repository\ProjectRepository;

final readonly class RoadmapBuilder
{
    public function __construct(
        private ScheduleLoader $scheduleLoader,
        private Scheduler $scheduler,
        private ProjectRepository $projectRepository,
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

        $projects = array_map(fn (Project $project): RoadmapRow => $this->projectRow($project, $window, $result, $overloaded, $data->overrunDays), $this->projectRepository->findAllForList());

        return new Roadmap($window, $data->today, $projects);
    }

    /**
     * @param array<int, true>               $overloaded
     * @param array<int, \DateTimeImmutable> $overrunDays
     */
    private function projectRow(Project $project, RoadmapWindow $window, ScheduleResult $result, array $overloaded, array $overrunDays): RoadmapRow
    {
        $lots = [];
        foreach ($project->getLots() as $lot) {
            if (!$lot->isSubLot()) {
                $lots[] = $lot->isLeaf() ? $this->leafRow($lot, $window, $result, $overloaded, $overrunDays) : $this->splitLotRow($lot, $window, $result, $overloaded, $overrunDays);
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
     * @param array<int, true>               $overloaded
     * @param array<int, \DateTimeImmutable> $overrunDays
     */
    private function splitLotRow(Lot $lot, RoadmapWindow $window, ScheduleResult $result, array $overloaded, array $overrunDays): RoadmapRow
    {
        $children = array_map(fn (Lot $child): RoadmapRow => $this->leafRow($child, $window, $result, $overloaded, $overrunDays), $lot->getChildren()->getValues());

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
     * @param array<int, true>               $overloaded
     * @param array<int, \DateTimeImmutable> $overrunDays
     */
    private function leafRow(Lot $leaf, RoadmapWindow $window, ScheduleResult $result, array $overloaded, array $overrunDays): RoadmapRow
    {
        $lotId = (int) $leaf->getId();
        $schedule = $result->get($lotId);
        if (null === $schedule) {
            return new RoadmapRow($leaf->getProject(), $leaf);
        }

        $signals = self::signalsOf($schedule);
        if (isset($overloaded[$lotId])) {
            $signals[] = RoadmapSignal::ToReplan;
        }

        $members = $leaf->getMembers()->getValues();
        if (!$schedule->isPlanned()) {
            return new RoadmapRow($leaf->getProject(), $leaf, remainingQuarters: $schedule->remainingQuarters, members: $members, signals: $signals);
        }

        $start = $schedule->start() ?? $leaf->getStartDate();
        $lastDay = $schedule->end() ?? $schedule->realizedTo;
        [$realized, $overrun] = self::realizedBars($schedule, $window, $overrunDays[$lotId] ?? null);

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
        );
    }

    /**
     * The days entered, split on the day the estimate was gone beyond.
     *
     * @return array{RoadmapBar|null, RoadmapBar|null} within the estimate, then beyond it
     */
    private static function realizedBars(LeafSchedule $schedule, RoadmapWindow $window, ?\DateTimeImmutable $overrunDay): array
    {
        $from = $schedule->realizedFrom;
        $to = $schedule->realizedTo;
        if (null === $from || null === $to) {
            return [null, null];
        }
        if (null === $overrunDay) {
            return [$window->bar($from, $to), null];
        }

        return [$overrunDay > $from ? $window->bar($from, $overrunDay->modify('-1 day')) : null, $window->bar($overrunDay, $to)];
    }

    /**
     * @return list<RoadmapSignal>
     */
    private static function signalsOf(LeafSchedule $schedule): array
    {
        $flags = [
            [RoadmapSignal::ToEstimate, $schedule->toEstimate],
            [RoadmapSignal::WithoutStart, $schedule->withoutStart],
            [RoadmapSignal::WithoutTeam, $schedule->withoutTeam],
            [RoadmapSignal::LateStart, $schedule->lateStart],
            [RoadmapSignal::EstimateReached, $schedule->isEstimateReached()],
            [RoadmapSignal::Overrun, $schedule->isOverrun()],
            [RoadmapSignal::TeamToReview, $schedule->teamToReview],
        ];

        return array_values(array_map(static fn (array $flag): RoadmapSignal => $flag[0], array_filter($flags, static fn (array $flag): bool => $flag[1])));
    }
}
