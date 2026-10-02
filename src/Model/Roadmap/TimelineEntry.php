<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Entity\Lot;

/**
 * A run of days entered on a leaf of a project, as listed on the timeline of its page.
 */
final readonly class TimelineEntry
{
    public function __construct(
        public Lot $leaf,
        public RoadmapRun $run,
        public bool $beyondEstimate,
    ) {
    }

    /**
     * The runs of the leaves, in the order of the leaves, each within then beyond its estimate.
     *
     * @param array<int, Lot>                                       $leaves by lot id
     * @param array<int, array{list<RoadmapRun>, list<RoadmapRun>}> $runs   the runs within and beyond the estimate, by lot id
     *
     * @return list<self>
     */
    public static function ofLeaves(array $leaves, array $runs): array
    {
        $entries = [];
        foreach ($leaves as $lotId => $leaf) {
            [$within, $beyond] = $runs[$lotId] ?? [[], []];
            array_push(
                $entries,
                ...array_map(static fn (RoadmapRun $run): self => new self($leaf, $run, false), $within),
                ...array_map(static fn (RoadmapRun $run): self => new self($leaf, $run, true), $beyond),
            );
        }

        return $entries;
    }

    /**
     * « Lot · Sous-lot » for a sub-lot, the title of the lot otherwise.
     */
    public function leafPath(): string
    {
        $parent = $this->leaf->getParent();

        return (null === $parent ? '' : $parent->getTitle() . ' · ') . $this->leaf->getTitle();
    }
}
