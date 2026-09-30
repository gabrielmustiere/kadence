<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\Project;
use App\Enum\Type\RoadmapSignal;

/**
 * A line of the roadmap: a project or a split lot with the span of its leaves, or a leaf with its past and future parts.
 */
final readonly class RoadmapRow
{
    /**
     * @param \DateTimeImmutable|null $end      the calculated end, null when it is unknown
     * @param \DateTimeImmutable|null $lastDay  the last day the bars reach
     * @param RoadmapBar|null         $overrun  the days entered once the estimate was gone beyond
     * @param list<LotMember>         $members
     * @param list<RoadmapSignal>     $signals
     * @param list<RoadmapRow>        $children
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
     * Time entered beyond the estimate, 0 when within it.
     */
    public function overrunQuarters(): int
    {
        return max(0, -($this->remainingQuarters ?? 0));
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
