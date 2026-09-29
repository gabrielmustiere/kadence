<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueProjectTitle extends Constraint
{
    public string $message = 'Un projet porte déjà ce titre.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
