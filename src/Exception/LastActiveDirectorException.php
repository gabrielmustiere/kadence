<?php

declare(strict_types=1);

namespace App\Exception;

final class LastActiveDirectorException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Kadence doit toujours garder au moins un membre de la direction actif.');
    }
}
