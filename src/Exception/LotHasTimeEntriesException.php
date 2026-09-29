<?php

declare(strict_types=1);

namespace App\Exception;

final class LotHasTimeEntriesException extends \DomainException
{
    public function __construct(?string $title)
    {
        parent::__construct(\sprintf('Des temps sont saisis sur « %s » : il ne peut plus être supprimé.', $title));
    }
}
