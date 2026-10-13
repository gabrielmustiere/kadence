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
    case Completed = 'completed';
    case ProgressToRefresh = 'progress_to_refresh';
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
            self::Completed => 'terminé à 100 %',
            self::ProgressToRefresh => 'avancement à actualiser',
            self::TeamToReview => 'équipe à revoir',
            self::ToReplan => 'à replanifier',
            self::PartialPlanning => 'planification partielle',
            self::Unsplit => 'à découper',
        };
    }

    public function meaning(): string
    {
        return match ($this) {
            self::ToEstimate => 'Sans estimation, ni le restant ni la fin du lot ne peuvent être calculés : rien n\'est placé après aujourd\'hui.',
            self::WithoutStart => 'Le restant du lot n\'est pas placé sur la frise : il n\'a pas de fin calculée.',
            self::WithoutTeam => 'Le restant du lot n\'est réparti sur la capacité de personne : il n\'a pas de fin calculée.',
            self::LateStart => 'Le travail aurait déjà dû commencer : le restant est replacé à partir de demain.',
            self::EstimateReached => 'Le lot se termine au dernier jour saisi. C\'est le cas normal d\'un lot terminé, sans alarme.',
            self::Overrun => 'Le travail peut continuer, mais rien ne dit combien de temps : sans avancement déclaré, la fin reste inconnue tant que l\'estimation n\'est pas révisée. Les jours saisis en dépassement apparaissent en rouge sur la barre.',
            self::Completed => 'Le lot se termine au dernier jour saisi.',
            self::ProgressToRefresh => 'La fin du lot est inconnue, comme celle de son lot découpé et de son projet, jusqu\'à une nouvelle déclaration d\'avancement.',
            self::TeamToReview => 'L\'équipe n\'apporte plus la capacité attendue : la fin calculée est repoussée, voire inconnue.',
            self::ToReplan => 'Une personne de l\'équipe est chargée au-delà de 100 % de sa capacité certains jours : la planification ne tient pas en l\'état.',
            self::PartialPlanning => 'La barre et la fin du projet ne couvrent que ses lots planifiés.',
            self::Unsplit => 'Le projet n\'a encore rien à placer sur la frise.',
        };
    }

    public function reason(): string
    {
        return match ($this) {
            self::ToEstimate => 'Aucune estimation n\'est saisie sur le lot.',
            self::WithoutStart => 'Aucune date de début n\'est posée sur le lot.',
            self::WithoutTeam => 'Personne n\'est affecté à l\'équipe du lot.',
            self::LateStart => 'La date de début est passée et aucun temps n\'est encore saisi sur le lot.',
            self::EstimateReached => 'Le temps saisi égale exactement l\'estimation, sans avancement déclaré.',
            self::Overrun => 'Le temps saisi dépasse l\'estimation du lot.',
            self::Completed => 'Son avancement est déclaré à 100 %.',
            self::ProgressToRefresh => 'Le temps saisi depuis la dernière déclaration d\'avancement a épuisé le restant qu\'elle annonçait.',
            self::TeamToReview => 'Un membre de l\'équipe est désactivé, ou l\'équipe ne couvre pas le restant dans les trois ans.',
            self::ToReplan => 'Un même jour, ce lot et d\'autres chargent une même personne à plus de 100 %, après une saisie plus lente que prévu, une estimation révisée, un avancement déclaré ou une capacité réduite.',
            self::PartialPlanning => 'Au moins un lot du projet n\'est pas planifié : il lui manque une estimation, une date de début ou une équipe.',
            self::Unsplit => 'Il n\'est pas découpé en lots : ce sont eux qui portent les estimations, les dates de début et les équipes.',
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
            [self::Completed, $schedule->isCompleted()],
            [self::ProgressToRefresh, $schedule->isProgressToRefresh()],
            [self::TeamToReview, $schedule->teamToReview],
        ];

        return array_values(array_map(static fn (array $flag): self => $flag[0], array_filter($flags, static fn (array $flag): bool => $flag[1])));
    }

    /**
     * Whether the signal tells why a leaf has no calculated end.
     */
    public function withholdsEnd(): bool
    {
        return match ($this) {
            self::ToEstimate, self::WithoutStart, self::WithoutTeam, self::Overrun, self::ProgressToRefresh, self::TeamToReview => true,
            self::LateStart, self::EstimateReached, self::Completed, self::ToReplan, self::PartialPlanning, self::Unsplit => false,
        };
    }

    /**
     * The badge variant of the design system.
     */
    public function variant(): string
    {
        return match ($this) {
            self::ToEstimate, self::LateStart, self::ProgressToRefresh => 'warning',
            self::Overrun, self::TeamToReview, self::ToReplan => 'danger',
            self::EstimateReached, self::Completed, self::WithoutStart, self::WithoutTeam, self::PartialPlanning, self::Unsplit => 'gray',
        };
    }
}
