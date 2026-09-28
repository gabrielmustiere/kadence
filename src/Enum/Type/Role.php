<?php

declare(strict_types=1);

namespace App\Enum\Type;

enum Role: string
{
    case Direction = 'direction';
    case Lead = 'lead';
    case Prod = 'prod';

    public function securityRole(): string
    {
        return 'ROLE_' . strtoupper($this->value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Direction => 'Direction',
            self::Lead => 'Lead',
            self::Prod => 'Prod',
        };
    }
}
