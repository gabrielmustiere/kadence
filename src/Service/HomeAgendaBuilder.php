<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\RoadmapSignal;
use App\Enum\Type\TimeOffStatus;
use App\Model\Home\HomeAgenda;
use App\Model\Home\WatchedProject;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Timesheet\TimesheetDay;
use App\Model\Week;
use App\Repository\TimeOffRequestRepository;
use Psr\Clock\ClockInterface;

/**
 * Reads what awaits a person in what the other screens already calculate: the signals of the roadmap, the forgotten
 * days of Ma semaine, the requests of Congés.
 */
final readonly class HomeAgendaBuilder
{
    /** The signals of a leaf that call for its owner. */
    public const array OWNER_SIGNALS = [RoadmapSignal::ToEstimate, RoadmapSignal::Overrun, RoadmapSignal::ProgressToRefresh];

    /** The signals of a leaf that call for a look at its project, beyond an unknown end. */
    public const array WATCH_SIGNALS = [RoadmapSignal::Overrun, RoadmapSignal::ToReplan, RoadmapSignal::TeamToReview, RoadmapSignal::LateStart, RoadmapSignal::ProgressToRefresh];

    /** How many days a decided request stays on the home page of its requester. */
    public const int RECENT_DAYS = 7;

    public function __construct(
        private RoadmapBuilder $roadmapBuilder,
        private TimesheetBuilder $timesheetBuilder,
        private TimeOffRequestRepository $timeOffRequestRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param bool $canDecide    whether the person decides on time off requests
     * @param bool $direction    whether they decide on everyone's
     * @param bool $withProjects whether they watch the projects, « à replanifier » included: the leads and the direction
     */
    public function build(User $user, bool $canDecide, bool $direction, bool $withProjects): HomeAgenda
    {
        $week = Week::containing($this->clock->now());
        $projects = $this->roadmapBuilder->build(RoadmapWindow::around($week), $withProjects)->projects;

        return new HomeAgenda(
            $this->forgottenDays($user, $week),
            $canDecide ? $this->timeOffRequestRepository->findPendingDecidableBy($user, $direction) : [],
            $this->leavesCallingFor($user, $projects),
            $withProjects ? self::watched($projects) : [],
            $this->requestsOf($user),
        );
    }

    /**
     * @return list<TimesheetDay>
     */
    private function forgottenDays(User $user, Week $week): array
    {
        $days = [...$this->timesheetBuilder->daysOf($user, $week->previous()), ...$this->timesheetBuilder->daysOf($user, $week)];

        return array_values(array_filter($days, static fn (TimesheetDay $day): bool => $day->forgotten));
    }

    /**
     * @param list<RoadmapRow> $projects
     *
     * @return list<RoadmapRow>
     */
    private function leavesCallingFor(User $user, array $projects): array
    {
        $leaves = array_merge(...array_map(self::activeLeavesOf(...), $projects));

        return array_values(array_filter(
            $leaves,
            static fn (RoadmapRow $leaf): bool => $leaf->lot?->getOwner()?->getId() === $user->getId()
                && array_any(self::OWNER_SIGNALS, $leaf->hasSignal(...)),
        ));
    }

    /**
     * @param list<RoadmapRow> $projects
     *
     * @return list<WatchedProject>
     */
    private static function watched(array $projects): array
    {
        $watched = [];
        foreach ($projects as $project) {
            $signals = self::watchSignalsOf($project);
            if ($project->isEndUnknown() || [] !== $signals) {
                $watched[] = new WatchedProject($project, $signals);
            }
        }

        return $watched;
    }

    /**
     * The watch signals its leaves raise, and those withholding the end of a leaf that leaves the project end unknown.
     *
     * @return list<RoadmapSignal> in the order of the cases
     */
    private static function watchSignalsOf(RoadmapRow $project): array
    {
        $unknownEnd = $project->unknownEndLeaves();
        $raised = [];
        foreach (self::activeLeavesOf($project) as $leaf) {
            $withheld = \in_array($leaf, $unknownEnd, true);
            foreach ($leaf->signals as $signal) {
                if (\in_array($signal, self::WATCH_SIGNALS, true) || ($withheld && $signal->withholdsEnd())) {
                    $raised[$signal->value] = true;
                }
            }
        }

        return array_values(array_filter(RoadmapSignal::cases(), static fn (RoadmapSignal $signal): bool => isset($raised[$signal->value])));
    }

    /**
     * The leaves of a project row in the order of its lots, but those declared done: an overrun stays raised on them,
     * though nothing is left to do.
     *
     * @return list<RoadmapRow>
     */
    private static function activeLeavesOf(RoadmapRow $project): array
    {
        $leaves = array_merge(...array_map(static fn (RoadmapRow $lot): array => $lot->isLeaf() ? [$lot] : $lot->children, $project->children));

        return array_values(array_filter($leaves, static fn (RoadmapRow $leaf): bool => !$leaf->hasSignal(RoadmapSignal::Completed)));
    }

    /**
     * The pending requests of the person, then those someone else decided on or cancelled lately, each by start day.
     *
     * @return list<TimeOffRequest>
     */
    private function requestsOf(User $user): array
    {
        $since = $this->clock->now()->modify(\sprintf('-%d days', self::RECENT_DAYS));
        $pending = [];
        $recent = [];
        foreach (array_reverse($this->timeOffRequestRepository->findOfUser($user)) as $request) {
            if (TimeOffStatus::Pending === $request->getStatus()) {
                $pending[] = $request;
            } elseif (self::settledByAnotherOn($request, $user) > $since) {
                $recent[] = $request;
            }
        }

        return [...$pending, ...$recent];
    }

    /**
     * When someone else than the person decided on the request or cancelled it; null when the person cancelled it, or
     * when it was approved at once for want of anyone to decide on it.
     */
    private static function settledByAnotherOn(TimeOffRequest $request, User $user): ?\DateTimeImmutable
    {
        return match ($request->getStatus()) {
            TimeOffStatus::Pending => null,
            TimeOffStatus::Approved, TimeOffStatus::Refused => null === $request->getDecidedBy() ? null : $request->getDecidedAt(),
            TimeOffStatus::Cancelled => $request->getCancelledBy()?->getId() === $user->getId() ? null : $request->getCancelledAt(),
        };
    }
}
