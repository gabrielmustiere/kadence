<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class EstimateCoversConsumed extends Constraint
{
    public string $belowMessage = '{{ consumed }} déjà saisis sur cette feuille : l\'estimation ne peut pas descendre sous {{ minimum }} j.';
    public string $removedMessage = '{{ consumed }} déjà saisis sur cette feuille : l\'estimation ne peut plus être retirée.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
