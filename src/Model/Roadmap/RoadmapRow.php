<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\Project;
use App\Enum\Type\RoadmapSignal;
use App\Model\Quarters;

/**
 * A line of the roadmap: a project or a split lot with the span of its leaves, or a leaf with its past and future parts.
 */
final readonly class RoadmapRow
{
    /**
     * @param \DateTimeImmutable|null $end          the calculated end, null when it is unknown
     * @param \DateTimeImmutable|null $lastDay      the last day the bars reach
     * @param RoadmapBar|null         $overrun      the days entered once the estimate was gone beyond
     * @param list<LotMember>         $members
     * @param list<RoadmapSignal>     $signals
     * @param list<RoadmapRow>        $children
     * @param list<RoadmapTeamLine>   $realizedTeam what each person entered within the estimate
     * @param list<RoadmapTeamLine>   $overrunTeam  what each person entered beyond the estimate
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
        public array $realizedTeam = [],
        public array $overrunTeam = [],
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

    /**
     * Time entered beyond the estimate in percent of it, rounded but never down to 0 % once overrun; null within it.
     */
    public function overrunPercent(): ?int
    {
        $estimate = $this->lot?->getEstimateDays();
        $overrun = $this->overrunQuarters();
        if (null === $estimate || 0 === $overrun) {
            return null;
        }

        return max(1, (int) round(100 * $overrun / ($estimate * Quarters::PER_DAY)));
    }

    /**
     * Time entered within the estimate, null while « à estimer ».
     */
    public function realizedQuarters(): ?int
    {
        $estimate = $this->lot?->getEstimateDays();
        if (null === $estimate || null === $this->remainingQuarters) {
            return null;
        }

        return $estimate * Quarters::PER_DAY - max(0, $this->remainingQuarters);
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
