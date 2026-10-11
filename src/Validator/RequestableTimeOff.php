<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class RequestableTimeOff extends Constraint
{
    public string $endBeforeStartMessage = 'La fin du congé précède son début.';
    public string $noWorkingDayMessage = 'Ces dates ne couvrent aucun jour ouvré : week-ends et jours fériés ne se posent pas.';
    public string $overlapMessage = 'Vous avez déjà une demande en attente ou validée le {{ day }} ({{ half }}).';
    public string $timeEnteredMessage = 'Vous avez saisi {{ entered }} le {{ day }} : retirez ce temps de votre semaine avant de poser ce congé.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
