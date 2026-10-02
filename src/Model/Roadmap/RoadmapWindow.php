<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Model\Week;

/**
 * The whole weeks shown on a frieze: on the roadmap, from 4 weeks before the anchor week to 36 weeks after it; on the
 * page of a project, the weeks of the project.
 */
final readonly class RoadmapWindow
{
    public const int WEEKS_BEFORE = 4;
    public const int WEEKS_AFTER = 36;
    public const int STEP = 4;
    public const int WEEK_COUNT = self::WEEKS_BEFORE + self::WEEKS_AFTER + 1;
    public const int MIN_SPANNING_WEEKS = 4;

    private function __construct(
        public Week $anchor,
        private \DateTimeImmutable $firstDay,
        private \DateTimeImmutable $lastDay,
    ) {
    }

    public static function around(Week $anchor): self
    {
        return new self(
            $anchor,
            $anchor->monday->modify(\sprintf('-%d weeks', self::WEEKS_BEFORE)),
            $anchor->monday->modify(\sprintf('+%d weeks', self::WEEKS_AFTER))->modify('+6 days'),
        );
    }

    /**
     * The weeks from the one of $from to the one of $to, at least MIN_SPANNING_WEEKS of them.
     */
    public static function spanning(\DateTimeImmutable $from, \DateTimeImmutable $to): self
    {
        $first = Week::containing($from);
        $lastMonday = max(Week::containing($to)->monday, $first->monday->modify(\sprintf('+%d weeks', self::MIN_SPANNING_WEEKS - 1)));

        return new self($first, $first->monday, $lastMonday->modify('+6 days'));
    }

    public function previous(): Week
    {
        return Week::containing($this->anchor->monday->modify(\sprintf('-%d weeks', self::STEP)));
    }

    public function next(): Week
    {
        return Week::containing($this->anchor->monday->modify(\sprintf('+%d weeks', self::STEP)));
    }

    public function firstDay(): \DateTimeImmutable
    {
        return $this->firstDay;
    }

    public function lastDay(): \DateTimeImmutable
    {
        return $this->lastDay;
    }

    public function weekCount(): int
    {
        return intdiv($this->dayCount(), 7);
    }

    public function contains(\DateTimeImmutable $day): bool
    {
        $date = $day->format('Y-m-d');

        return $date >= $this->firstDay()->format('Y-m-d') && $date <= $this->lastDay()->format('Y-m-d');
    }

    /**
     * The bar of the days from $from to $to included, or null when none of them is within the window.
     */
    public function bar(\DateTimeImmutable $from, \DateTimeImmutable $to): ?RoadmapBar
    {
        $days = $this->dayCount();
        $start = $this->offsetOf($from);
        $end = $this->offsetOf($to) + 1;
        if ($end <= 0 || $start >= $days || $end <= $start) {
            return null;
        }

        $left = max(0, $start);
        $right = min($days, $end);

        return new RoadmapBar(100 * $left / $days, 100 * ($right - $left) / $days, $start < 0, $end > $days, $from, $to);
    }

    /**
     * The bar from the first to the last day of the runs, holding a segment for each run within the window; null when
     * none of them is.
     *
     * @param list<RoadmapRun> $runs in date order
     */
    public function segmentedBar(array $runs): ?RoadmapBar
    {
        if ([] === $runs) {
            return null;
        }

        $bar = $this->bar($runs[0]->from, $runs[array_key_last($runs)]->to);
        if (null === $bar) {
            return null;
        }

        $segments = [];
        foreach ($runs as $run) {
            $placed = $this->bar($run->from, $run->to);
            if (null !== $placed) {
                $segments[] = new RoadmapSegment(new RoadmapBar(100 * ($placed->left - $bar->left) / $bar->width, 100 * $placed->width / $bar->width, $placed->cutStart, $placed->cutEnd, $run->from, $run->to), $run);
            }
        }

        return [] === $segments ? null : new RoadmapBar($bar->left, $bar->width, $bar->cutStart, $bar->cutEnd, $bar->from, $bar->to, $segments);
    }

    /**
     * Where a day falls on the window, in percent of its width.
     */
    public function position(\DateTimeImmutable $day): float
    {
        return 100 * $this->offsetOf($day) / $this->dayCount();
    }

    private function dayCount(): int
    {
        return $this->offsetOf($this->lastDay) + 1;
    }

    private function offsetOf(\DateTimeImmutable $day): int
    {
        return (int) $this->firstDay->diff($day->setTime(0, 0))->format('%r%a');
    }
}
