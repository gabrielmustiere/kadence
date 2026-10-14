<?php

declare(strict_types=1);

namespace App\Model\Home;

use App\Entity\TimeOffRequest;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Timesheet\TimesheetDay;

/**
 * What awaits the signed-in person on the home page: only what concerns them, plus the requests they decide on and the
 * projects a lead watches.
 */
final readonly class HomeAgenda
{
    /**
     * @param list<TimesheetDay>   $forgottenDays   the incomplete days of the previous week and of this one, oldest first
     * @param list<TimeOffRequest> $toDecide        the pending requests the person may decide on, nearest first
     * @param list<RoadmapRow>     $myLeaves        the leaves they own that call for them, in the order of the projects
     * @param list<WatchedProject> $watchedProjects by title
     * @param list<TimeOffRequest> $myRequests      their pending requests, then those decided lately, each by start day
     */
    public function __construct(
        public array $forgottenDays,
        public array $toDecide,
        public array $myLeaves,
        public array $watchedProjects,
        public array $myRequests,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->forgottenDays && [] === $this->toDecide && [] === $this->myLeaves && [] === $this->watchedProjects && [] === $this->myRequests;
    }
}
