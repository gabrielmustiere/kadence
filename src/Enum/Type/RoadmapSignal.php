<?php

declare(strict_types=1);

namespace App\Enum\Type;

use App\Model\Schedule\LeafSchedule;

enum RoadmapSignal: string
{
    case ToEstimate = 'to_estimate';
    case WithoutStart = 'without_start';
    case WithoutTeam = 'without_team';
    case LateStart = 'late_start';
    case EstimateReached = 'estimate_reached';
    case Overrun = 'overrun';
    case TeamToReview = 'team_to_review';
    case ToReplan = 'to_replan';
    case PartialPlanning = 'partial_planning';
    case Unsplit = 'unsplit';

    public function label(): string
    {
        return match ($this) {
            self::ToEstimate => 'à estimer',
            self::WithoutStart => 'sans début',
            self::WithoutTeam => 'sans équipe',
            self::LateStart => 'démarrage en retard',
            self::EstimateReached => 'estimation atteinte',
            self::Overrun => 'en dépassement',
            self::TeamToReview => 'équipe à revoir',
            self::ToReplan => 'à replanifier',
            self::PartialPlanning => 'planning partiel',
            self::Unsplit => 'à découper',
        };
    }

    /**
     * The signals of a leaf as its schedule raises them, « à replanifier » aside.
     *
     * @return list<self>
     */
    public static function of(LeafSchedule $schedule): array
    {
        $flags = [
            [self::ToEstimate, $schedule->toEstimate],
            [self::WithoutStart, $schedule->withoutStart],
            [self::WithoutTeam, $schedule->withoutTeam],
            [self::LateStart, $schedule->lateStart],
            [self::EstimateReached, $schedule->isEstimateReached()],
            [self::Overrun, $schedule->isOverrun()],
            [self::TeamToReview, $schedule->teamToReview],
        ];

        return array_values(array_map(static fn (array $flag): self => $flag[0], array_filter($flags, static fn (array $flag): bool => $flag[1])));
    }

    /**
     * The badge variant of the design system.
     */
    public function variant(): string
    {
        return match ($this) {
            self::ToEstimate, self::LateStart => 'warning',
            self::Overrun, self::TeamToReview, self::ToReplan => 'danger',
            self::EstimateReached, self::WithoutStart, self::WithoutTeam, self::PartialPlanning, self::Unsplit => 'gray',
        };
    }
}
