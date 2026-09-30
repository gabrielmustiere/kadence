<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AddableHoliday extends Constraint
{
    public string $weekendMessage = 'On n\'ajoute un jour férié que du lundi au vendredi.';
    public string $alreadyHolidayMessage = 'Le {{ day }} est déjà férié dans le calendrier {{ calendar }}.';
    public string $removedMessage = 'Le {{ day }} est un jour férié légal retiré du calendrier {{ calendar }} : annulez le retrait pour le rétablir.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
