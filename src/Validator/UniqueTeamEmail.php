<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueTeamEmail extends Constraint
{
    public string $message = 'Cet e-mail est déjà utilisé par un membre de l\'équipe.';
    public string $deactivatedMessage = 'Cet e-mail appartient à une personne désactivée : réactivez-la depuis la liste de l\'équipe plutôt que de la réinscrire.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
