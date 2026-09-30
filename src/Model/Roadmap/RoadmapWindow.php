<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Model\Week;

/**
 * The weeks shown on the roadmap: from 4 weeks before the anchor week to 36 weeks after it.
 */
final readonly class RoadmapWindow
{
    public const int WEEKS_BEFORE = 4;
    public const int WEEKS_AFTER = 36;
    public const int STEP = 4;
    public const int WEEK_COUNT = self::WEEKS_BEFORE + self::WEEKS_AFTER + 1;

    private function __construct(
        public Week $anchor,
    ) {
    }

    public static function around(Week $anchor): self
    {
        return new self($anchor);
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
        return $this->anchor->monday->modify(\sprintf('-%d weeks', self::WEEKS_BEFORE));
    }

    public function lastDay(): \DateTimeImmutable
    {
        return $this->anchor->monday->modify(\sprintf('+%d weeks', self::WEEKS_AFTER))->modify('+6 days');
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
     * Where a day falls on the window, in percent of its width.
     */
    public function position(\DateTimeImmutable $day): float
    {
        return 100 * $this->offsetOf($day) / $this->dayCount();
    }

    private function dayCount(): int
    {
        return 7 * self::WEEK_COUNT;
    }

    private function offsetOf(\DateTimeImmutable $day): int
    {
        return (int) $this->firstDay()->diff($day->setTime(0, 0))->format('%r%a');
    }
}
