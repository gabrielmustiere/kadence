<?php

declare(strict_types=1);

namespace App\Exception;

final class LotDepthException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Un sous-lot ne peut pas lui-même être découpé en sous-lots.');
    }
}
