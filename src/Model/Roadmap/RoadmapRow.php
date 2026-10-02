<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\Project;
use App\Enum\Type\RoadmapSignal;
use App\Model\Quarters;
use App\Model\Schedule\LeafProgress;

/**
 * A line of the roadmap: a project or a split lot with the span of its leaves, or a leaf with its past and future parts.
 */
final readonly class RoadmapRow
{
    /**
     * @param \DateTimeImmutable|null $end             the calculated end, null when it is unknown
     * @param \DateTimeImmutable|null $lastDay         the last day the bars reach
     * @param RoadmapBar|null         $overrun         the days entered once the estimate was gone beyond
     * @param list<LotMember>         $members
     * @param list<RoadmapSignal>     $signals
     * @param list<RoadmapRow>        $children
     * @param \DateTimeImmutable|null $enteredFrom     first day entered on the leaf
     * @param \DateTimeImmutable|null $enteredTo       last day entered on the leaf
     * @param int                     $enteredDayCount days with time entered on the leaf
     * @param list<RoadmapTeamLine>   $team            the members with their share and what they entered on the leaf,
     *                                                 then the people outside the team who entered time on it
     * @param int                     $overrunQuarters time entered beyond the estimate, 0 within it
     * @param LeafProgress|null       $progress        the progress in force on the leaf
     */
    public function __construct(
        public Project $project,
        public ?Lot $lot,
        public ?\DateTimeImmutable $start = null,
        public ?\DateTimeImmutable $end = null,
        public ?\DateTimeImmutable $lastDay = null,
        public ?RoadmapBar $span = null,
        public ?RoadmapBar $realized = null,
        public ?RoadmapBar $overrun = null,
        public ?RoadmapBar $future = null,
        public ?int $remainingQuarters = null,
        public array $members = [],
        public array $signals = [],
        public array $children = [],
        public ?\DateTimeImmutable $enteredFrom = null,
        public ?\DateTimeImmutable $enteredTo = null,
        public int $enteredDayCount = 0,
        public array $team = [],
        public int $overrunQuarters = 0,
        public ?LeafProgress $progress = null,
    ) {
    }

    public function title(): string
    {
        return (string) ($this->lot?->getTitle() ?? $this->project->getTitle());
    }

    public function isLeaf(): bool
    {
        return null !== $this->lot && $this->lot->isLeaf();
    }

    /**
     * Time entered on the leaf, whoever entered it.
     */
    public function enteredQuarters(): int
    {
        return array_sum(array_map(static fn (RoadmapTeamLine $line): int => $line->quarters, $this->team));
    }

    /**
     * Time entered beyond the estimate in percent of it, rounded but never down to 0 % once overrun; null within it.
     */
    public function overrunPercent(): ?int
    {
        $estimate = $this->lot?->getEstimateDays();
        if (null === $estimate || 0 === $this->overrunQuarters) {
            return null;
        }

        return max(1, (int) round(100 * $this->overrunQuarters / ($estimate * Quarters::PER_DAY)));
    }

    /**
     * What the leaf will have cost once done, according to the progress in force; null without one.
     */
    public function projectedQuarters(): ?int
    {
        return null === $this->progress ? null : $this->enteredQuarters() + ($this->remainingQuarters ?? 0);
    }

    /**
     * How far the projected cost of the leaf goes beyond its estimate, below 0 when it stays under; null without progress.
     */
    public function projectedGapQuarters(): ?int
    {
        $projected = $this->projectedQuarters();

        return null === $projected ? null : $projected - ($this->lot?->getEstimateDays() ?? 0) * Quarters::PER_DAY;
    }

    public function isEndUnknown(): bool
    {
        return null !== $this->start && null === $this->end;
    }

    public function hasSignal(RoadmapSignal $signal): bool
    {
        return \in_array($signal, $this->signals, true);
    }
}
