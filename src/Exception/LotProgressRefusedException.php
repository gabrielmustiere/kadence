<?php

declare(strict_types=1);

namespace App\Exception;

final class LotProgressRefusedException extends \DomainException
{
    public static function invalidPercent(): self
    {
        return new self('Un avancement se déclare de 0 à 100 %, par pas de 5 %.');
    }

    public static function declaredMeanwhile(?string $title): self
    {
        return new self(\sprintf('Un avancement vient d\'être déclaré sur « %s » : rechargez la page avant de le modifier.', $title));
    }

    public static function completeWithoutTime(?string $title): self
    {
        return new self(\sprintf('Aucun temps n\'est saisi sur « %s » : son avancement ne peut pas être de 100 %%.', $title));
    }
}
