<?php

declare(strict_types=1);

namespace App\Model\Schedule;

/**
 * The schedules of the leaves, and the load they put on each person: the sum of their shares, day by day, over the
 * future part of the leaves they are a member of.
 */
final class ScheduleResult
{
    public const int FULL_LOAD = 100;

    /** @var array<int, LeafSchedule> */
    private array $schedules = [];

    /** @var array<int, array<string, int>> summed shares by user id and day (Y-m-d) */
    private array $loads = [];

    /** @var array<int, array<string, list<int>>> lot ids by user id and day (Y-m-d) */
    private array $contributors = [];

    /** @var array<int, true> */
    private array $overloaded = [];

    /**
     * @param array<int, array<string, int>> $load share by user id and day (Y-m-d)
     */
    public function add(LeafSchedule $schedule, array $load): void
    {
        $this->schedules[$schedule->lotId] = $schedule;
        foreach ($load as $userId => $days) {
            foreach ($days as $day => $share) {
                $this->loads[$userId][$day] = ($this->loads[$userId][$day] ?? 0) + $share;
                $this->contributors[$userId][$day][] = $schedule->lotId;
                if ($this->loads[$userId][$day] > self::FULL_LOAD) {
                    $this->overloaded += array_fill_keys($this->contributors[$userId][$day], true);
                }
            }
        }
    }

    public function get(int $lotId): ?LeafSchedule
    {
        return $this->schedules[$lotId] ?? null;
    }

    public function loadAt(int $userId, string $day): int
    {
        return $this->loads[$userId][$day] ?? 0;
    }

    /**
     * @return list<int>
     */
    public function contributorsAt(int $userId, string $day): array
    {
        return $this->contributors[$userId][$day] ?? [];
    }

    /**
     * @return array<int, true> the leaves that load someone beyond a full load on some day, by lot id
     */
    public function overloadedLots(): array
    {
        return $this->overloaded;
    }
}
