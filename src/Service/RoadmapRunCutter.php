<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Lot;
use App\Entity\User;
use App\Model\Roadmap\RoadmapRun;
use App\Model\Roadmap\RoadmapTeamLine;
use App\Model\Schedule\PlannedMember;
use App\Model\Schedule\ScheduleData;
use App\Repository\TimeEntryRepository;

final readonly class RoadmapRunCutter
{
    public function __construct(
        private TimeEntryRepository $timeEntryRepository,
    ) {
    }

    /**
     * The days entered on each of these leaves, within its estimate then beyond it, each cut into runs.
     *
     * @param list<int> $lotIds
     *
     * @return array<int, array{list<RoadmapRun>, list<RoadmapRun>}> the runs within and beyond the estimate, by lot id
     */
    public function cut(ScheduleData $data, array $lotIds): array
    {
        $runs = [];
        foreach ($this->timeEntryRepository->sumQuartersByDayAndUserForLots($lotIds) as $lotId => $quartersByDay) {
            $leaf = $data->leaves[$lotId] ?? null;
            if (null === $leaf) {
                continue;
            }

            $overrunDay = ($data->overrunDays[$lotId][1] ?? null)?->format('Y-m-d');
            $within = array_filter($quartersByDay, static fn (string $day): bool => null === $overrunDay || $day < $overrunDay, \ARRAY_FILTER_USE_KEY);
            $beyond = array_filter($quartersByDay, static fn (string $day): bool => null !== $overrunDay && $day >= $overrunDay, \ARRAY_FILTER_USE_KEY);

            $members = array_map(static fn (PlannedMember $member): int => $member->userId, $data->plans[$lotId]->members ?? []);
            if ([] === $members) {
                $members = array_keys(array_replace([], ...array_values($quartersByDay)));
            }

            $runs[$lotId] = [self::runs($within, $members, $leaf, $data), self::runs($beyond, $members, $leaf, $data)];
        }

        return $runs;
    }

    /**
     * Days entered make one run until a working day of one of these people goes by without any time entered: the
     * members of the leaf, or the people who entered time on it when it has no team.
     *
     * @param array<string, array<int, int>> $quartersByDay quarters entered by each person each day (Y-m-d, in date order)
     * @param list<int>                      $members       user ids
     *
     * @return list<RoadmapRun>
     */
    private static function runs(array $quartersByDay, array $members, Lot $leaf, ScheduleData $data): array
    {
        $runs = [];
        $run = [];
        $lastDay = null;
        foreach ($quartersByDay as $day => $quartersByUser) {
            $date = new \DateTimeImmutable($day);
            if (null !== $lastDay && $data->capacity->hasWorkingDayBetween($members, $lastDay, $date)) {
                $runs[] = self::run($run, $leaf, $data->people);
                $run = [];
            }
            $run[$day] = $quartersByUser;
            $lastDay = $date;
        }

        return [] === $run ? $runs : [...$runs, self::run($run, $leaf, $data->people)];
    }

    /**
     * @param non-empty-array<string, array<int, int>> $quartersByDay quarters entered by each person each day (Y-m-d, in date order)
     * @param array<int, User>                         $people        by user id
     */
    private static function run(array $quartersByDay, Lot $leaf, array $people): RoadmapRun
    {
        $quartersByUser = [];
        foreach ($quartersByDay as $entered) {
            foreach ($entered as $userId => $quarters) {
                $quartersByUser[$userId] = ($quartersByUser[$userId] ?? 0) + $quarters;
            }
        }
        $days = array_keys($quartersByDay);

        return new RoadmapRun(new \DateTimeImmutable($days[0]), new \DateTimeImmutable($days[array_key_last($days)]), array_sum($quartersByUser), \count($days), RoadmapTeamLine::forLeaf($leaf, $quartersByUser, $people));
    }
}
