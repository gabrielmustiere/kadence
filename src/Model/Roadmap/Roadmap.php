<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

final readonly class Roadmap
{
    private const array MONTHS = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    /**
     * The narrowest track of the roadmap: 72rem of table less the 20rem label column. A longer window widens it so that
     * a day never gets narrower than on the roadmap.
     */
    private const float MIN_TRACK_REM = 52.0;

    /**
     * A month cut by the left edge is left unlabelled when less of it than this (in percent of the narrowest track of
     * the roadmap) is shown: its label, year included, takes up to 63px, i.e. 8% of that track.
     */
    private const float MIN_MONTH_WIDTH = 9.0;

    /**
     * @param list<RoadmapRow> $projects
     */
    public function __construct(
        public RoadmapWindow $window,
        public \DateTimeImmutable $today,
        public array $projects,
    ) {
    }

    public function todayPosition(): ?float
    {
        return $this->window->contains($this->today) ? $this->window->position($this->today) : null;
    }

    /**
     * Where today falls on the window, or the edge nearest to it when it falls outside.
     */
    public function centerPosition(): float
    {
        return max(0.0, min(100.0, $this->window->position($this->today)));
    }

    public function minTrackRem(): float
    {
        return max(self::MIN_TRACK_REM, self::MIN_TRACK_REM * $this->window->weekCount() / RoadmapWindow::WEEK_COUNT);
    }

    /**
     * The months of the window with where they begin, the first one starting at the left edge.
     *
     * @return list<array{string, float}> label and position in percent
     */
    public function months(): array
    {
        $months = [];
        $first = $this->window->firstDay();
        $minWidth = self::MIN_MONTH_WIDTH * self::MIN_TRACK_REM / $this->minTrackRem();
        for ($day = $first->modify('first day of this month'); $day <= $this->window->lastDay(); $day = $day->modify('first day of next month')) {
            if ($day < $first && $this->window->position($day->modify('first day of next month')) < $minWidth) {
                continue;
            }

            $month = (int) $day->format('n');
            $months[] = [self::MONTHS[$month] . (1 === $month || [] === $months ? ' ' . $day->format('Y') : ''), max(0.0, $this->window->position($day))];
        }

        return $months;
    }
}
