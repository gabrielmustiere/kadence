<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueTagLabel extends Constraint
{
    public string $message = 'Un tag de cette catégorie porte déjà ce libellé.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
