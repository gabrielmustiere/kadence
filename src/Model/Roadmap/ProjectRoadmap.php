<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

/**
 * The page of a project: its frieze, on a roadmap of that project alone spanning its days, then its timeline.
 */
final readonly class ProjectRoadmap
{
    /**
     * @param list<TimelineMonth> $timeline
     * @param bool                $dated    whether a leaf of the project has a start or time entered, without which
     *                                      there is no frieze to draw
     */
    public function __construct(
        public Roadmap $roadmap,
        public RoadmapRow $project,
        public array $timeline,
        public bool $dated,
    ) {
    }
}
