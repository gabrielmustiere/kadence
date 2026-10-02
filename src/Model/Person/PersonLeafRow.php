<?php

declare(strict_types=1);

namespace App\Model\Person;

use App\Entity\Lot;
use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\LeafPath;
use App\Model\Roadmap\RoadmapBar;

/**
 * A leaf on the page of a person: what the person entered on it and, when in its team, their share of its future part.
 */
final readonly class PersonLeafRow
{
    /**
     * @param int|null                $share    null when the person is not in the team of the leaf
     * @param \DateTimeImmutable|null $start    first day of the future part of the leaf
     * @param \DateTimeImmutable|null $end      the calculated end of the leaf, null when it is unknown
     * @param RoadmapBar|null         $realized the runs of the person within the estimate
     * @param RoadmapBar|null         $overrun  the runs of the person beyond the estimate
     * @param RoadmapBar|null         $future   the future part of the leaf, when the person is in its team
     * @param list<RoadmapSignal>     $signals
     */
    public function __construct(
        public Lot $lot,
        public ?int $share,
        public ?\DateTimeImmutable $start,
        public ?\DateTimeImmutable $end,
        public ?RoadmapBar $realized,
        public ?RoadmapBar $overrun,
        public ?RoadmapBar $future,
        public array $signals,
    ) {
    }

    public function leafPath(): string
    {
        return LeafPath::of($this->lot);
    }

    /**
     * The person is in the team of a leaf not over yet: its end falls after today, or is unknown.
     */
    public function isUpcoming(\DateTimeImmutable $today): bool
    {
        return null !== $this->share && (null === $this->end || $this->end->format('Y-m-d') > $today->format('Y-m-d'));
    }
}
