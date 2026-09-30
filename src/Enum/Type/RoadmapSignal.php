<?php

declare(strict_types=1);

namespace App\Enum\Type;

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
