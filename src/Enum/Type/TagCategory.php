<?php

declare(strict_types=1);

namespace App\Enum\Type;

enum TagCategory: string
{
    case TechnicalSkill = 'competence';
    case FunctionalExperience = 'experience';
    case TeamType = 'equipe';

    public function label(): string
    {
        return match ($this) {
            self::TechnicalSkill => 'Compétence technique',
            self::FunctionalExperience => 'Expérience fonctionnelle',
            self::TeamType => 'Type d\'équipe',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::TechnicalSkill => 'Compétences techniques',
            self::FunctionalExperience => 'Expériences fonctionnelles',
            self::TeamType => 'Types d\'équipe',
        };
    }

    public function allowsMany(): bool
    {
        return self::TeamType !== $this;
    }
}
