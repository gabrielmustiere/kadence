<?php

declare(strict_types=1);

namespace App\Model\Person;

use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\TimelineMonth;

/**
 * The page of a person: their frieze, on a roadmap spanning their days, what is coming for them, then their timeline.
 */
final readonly class PersonRoadmap
{
    /**
     * @param Roadmap             $roadmap  the window and today, without project rows
     * @param list<PersonProject> $projects in the order of the roadmap
     * @param list<PersonLeafRow> $upcoming the leaves of their teams not over yet, by first day of their future part
     * @param list<TimelineMonth> $timeline
     * @param bool                $dated    whether the person entered time or is in the team of a leaf with a future
     *                                      part, without which there is no frieze to draw
     * @param PersonLoad|null     $load     null when it is not to be shown, or when the person is deactivated
     */
    public function __construct(
        public Roadmap $roadmap,
        public array $projects,
        public array $upcoming,
        public array $timeline,
        public bool $dated,
        public ?PersonLoad $load,
    ) {
    }

    /**
     * The projects the person entered time on, from the latest worked on to the earliest.
     *
     * @return list<PersonProject>
     */
    public function enteredProjects(): array
    {
        $entered = array_values(array_filter($this->projects, static fn (PersonProject $project): bool => null !== $project->lastEnteredDay));
        usort($entered, static fn (PersonProject $a, PersonProject $b): int => $b->lastEnteredDay <=> $a->lastEnteredDay);

        return $entered;
    }
}
