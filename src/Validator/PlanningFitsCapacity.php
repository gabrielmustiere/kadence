<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class PlanningFitsCapacity extends Constraint
{
    public string $message = 'La charge de {{ person }} atteindrait {{ load }} % le {{ day }}, avec « {{ leaf }} » ({{ project }}). Décalez la date de début ou réduisez une part.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
