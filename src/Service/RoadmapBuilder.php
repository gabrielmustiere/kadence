<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\RoadmapBar;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapTeamLine;
use App\Model\Roadmap\RoadmapWindow;
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
        $teams = $this->teams($data);

        $projects = array_map(fn (Project $project): RoadmapRow => $this->projectRow($project, $window, $result, $overloaded, $data->overrunDays, $teams), $this->projectRepository->findAllForList());

        return new Roadmap($window, $data->today, $projects);
    }

    /**
     * @param array<int, true>                                                $overloaded
     * @param array<int, array{\DateTimeImmutable|null, \DateTimeImmutable}>  $overrunDays
     * @param array<int, array{list<RoadmapTeamLine>, list<RoadmapTeamLine>}> $teams
     */
    private function projectRow(Project $project, RoadmapWindow $window, ScheduleResult $result, array $overloaded, array $overrunDays, array $teams): RoadmapRow
    {
        $lots = [];
        foreach ($project->getLots() as $lot) {
            if (!$lot->isSubLot()) {
                $lots[] = $lot->isLeaf() ? $this->leafRow($lot, $window, $result, $overloaded, $overrunDays, $teams) : $this->splitLotRow($lot, $window, $result, $overloaded, $overrunDays, $teams);
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
     * @param array<int, true>                                                $overloaded
     * @param array<int, array{\DateTimeImmutable|null, \DateTimeImmutable}>  $overrunDays
     * @param array<int, array{list<RoadmapTeamLine>, list<RoadmapTeamLine>}> $teams
     */
    private function splitLotRow(Lot $lot, RoadmapWindow $window, ScheduleResult $result, array $overloaded, array $overrunDays, array $teams): RoadmapRow
    {
        $children = array_map(fn (Lot $child): RoadmapRow => $this->leafRow($child, $window, $result, $overloaded, $overrunDays, $teams), $lot->getChildren()->getValues());

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
     * @param array<int, true>                                                $overloaded
     * @param array<int, array{\DateTimeImmutable|null, \DateTimeImmutable}>  $overrunDays
     * @param array<int, array{list<RoadmapTeamLine>, list<RoadmapTeamLine>}> $teams
     */
    private function leafRow(Lot $leaf, RoadmapWindow $window, ScheduleResult $result, array $overloaded, array $overrunDays, array $teams): RoadmapRow
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
        [$realizedTeam, $overrunTeam] = $teams[$lotId] ?? [[], []];

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
            realizedTeam: $realizedTeam,
            overrunTeam: $overrunTeam,
        );
    }

    /**
     * What each person entered on every leaf with time entered, within its estimate then beyond it.
     *
     * @return array<int, array{list<RoadmapTeamLine>, list<RoadmapTeamLine>}> by lot id
     */
    private function teams(ScheduleData $data): array
    {
        $beyond = [];
        foreach ($this->timeEntryRepository->findQuartersInOrderForLots(array_keys($data->overrunDays)) as $lotId => $entries) {
            $beyond[$lotId] = self::beyondEstimate($data->plans[$lotId]->estimateQuarters ?? 0, $entries);
        }

        $teams = [];
        foreach ($this->timeEntryRepository->sumQuartersByLotAndUser() as $lotId => $entered) {
            $leaf = $data->leaves[$lotId] ?? null;
            if (null === $leaf) {
                continue;
            }

            $overrun = $beyond[$lotId] ?? [];
            $within = [];
            foreach ($entered as $userId => $quarters) {
                $within[$userId] = $quarters - ($overrun[$userId] ?? 0);
            }
            $teams[$lotId] = [self::team($leaf, $within, $data->people), self::team($leaf, $overrun, $data->people)];
        }

        return $teams;
    }

    /**
     * The estimate is used up in the order the time was entered: what comes once it is reached is beyond it.
     *
     * @param list<array{int, int}> $entries user id and quarters, in the order they were entered
     *
     * @return array<int, int> quarters entered beyond the estimate, by user id
     */
    private static function beyondEstimate(int $estimateQuarters, array $entries): array
    {
        $entered = 0;
        $beyond = [];
        foreach ($entries as [$userId, $quarters]) {
            $entered += $quarters;
            $over = min($quarters, $entered - $estimateQuarters);
            if ($over > 0) {
                $beyond[$userId] = ($beyond[$userId] ?? 0) + $over;
            }
        }

        return $beyond;
    }

    /**
     * @param array<int, int>  $quartersByUser
     * @param array<int, User> $people         by user id
     *
     * @return list<RoadmapTeamLine> the members with their share, then the people outside the team by time entered
     */
    private static function team(Lot $leaf, array $quartersByUser, array $people): array
    {
        $lines = [];
        foreach ($leaf->getMembers() as $member) {
            $userId = (int) $member->getUser()->getId();
            $lines[] = new RoadmapTeamLine($member->getUser(), $member->getShare(), $quartersByUser[$userId] ?? 0);
            unset($quartersByUser[$userId]);
        }

        arsort($quartersByUser);
        foreach (array_filter($quartersByUser) as $userId => $quarters) {
            if (isset($people[$userId])) {
                $lines[] = new RoadmapTeamLine($people[$userId], null, $quarters);
            }
        }

        return $lines;
    }

    /**
     * The days entered, split between the last day entered within the estimate and the day it was gone beyond.
     *
     * @param array{\DateTimeImmutable|null, \DateTimeImmutable}|null $overrunDays
     *
     * @return array{RoadmapBar|null, RoadmapBar|null} within the estimate, then beyond it
     */
    private static function realizedBars(LeafSchedule $schedule, RoadmapWindow $window, ?array $overrunDays): array
    {
        $from = $schedule->realizedFrom;
        $to = $schedule->realizedTo;
        if (null === $from || null === $to) {
            return [null, null];
        }
        if (null === $overrunDays) {
            return [$window->bar($from, $to), null];
        }

        [$lastDayWithin, $overrunDay] = $overrunDays;

        return [null === $lastDayWithin ? null : $window->bar($from, $lastDayWithin), $window->bar($overrunDay, $to)];
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
