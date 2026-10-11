<?php

declare(strict_types=1);

namespace App\Exception;

final class TimeOffRefusedException extends \DomainException
{
    public static function alreadyDecided(): self
    {
        return new self('Cette demande vient d\'être décidée ou annulée : rechargez la page.');
    }

    public static function reasonMissing(): self
    {
        return new self('Indiquez le motif du refus : la personne le verra.');
    }

    public static function notCancellable(): self
    {
        return new self('Ce congé ne peut plus être annulé.');
    }
}
