<?php

declare(strict_types=1);

namespace App\Exception;

use App\Model\Quarters;

final class TimeEntryRefusedException extends \DomainException
{
    public static function invalidQuarters(): self
    {
        return new self('Une case vaut 0, ¼, ½, ¾ ou 1 journée.');
    }

    public static function notALeaf(?string $title): self
    {
        return new self(\sprintf('« %s » est découpé en sous-lots : saisissez sur l\'un d\'eux.', $title));
    }

    public static function weekend(): self
    {
        return new self('On ne saisit pas de temps le week-end.');
    }

    public static function future(): self
    {
        return new self('On ne peut pas saisir un temps sur un jour à venir.');
    }

    public static function holiday(\DateTimeImmutable $day, string $label): self
    {
        return new self(\sprintf('Le %s est férié (%s) : on n\'y saisit pas de temps.', $day->format('d/m'), $label));
    }

    public static function dayFull(\DateTimeImmutable $day, int $remainingQuarters): self
    {
        return new self(0 >= $remainingQuarters
            ? \sprintf('La journée du %s est déjà complète.', $day->format('d/m'))
            : \sprintf('Il ne reste que %s à saisir le %s.', Quarters::toDays($remainingQuarters), $day->format('d/m')));
    }

    public static function weekFull(int $maxQuarters, int $remainingQuarters): self
    {
        return new self(0 >= $remainingQuarters
            ? \sprintf('Votre semaine a atteint son maximum de %s.', Quarters::toDays($maxQuarters))
            : \sprintf('Il ne reste que %s à saisir cette semaine (maximum %s).', Quarters::toDays($remainingQuarters), Quarters::toDays($maxQuarters)));
    }
}
