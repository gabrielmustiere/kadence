<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoManagementCycle extends Constraint
{
    public string $message = 'Cette personne manage déjà {{ manager }}, directement ou non : en faire son manager formerait une boucle.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
