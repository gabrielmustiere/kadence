<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueLotTitle extends Constraint
{
    public string $lotMessage = 'Un lot de ce projet porte déjà ce titre.';
    public string $subLotMessage = 'Un sous-lot de ce lot porte déjà ce titre.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
