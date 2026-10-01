<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\User;

final class ManagerWithActiveReportsException extends \DomainException
{
    /**
     * @param non-empty-list<User> $reports the active people who name the manager
     */
    public function __construct(User $manager, array $reports)
    {
        $names = array_map(static fn (User $user): string => \sprintf('%s %s', $user->getFirstName(), $user->getLastName()), $reports);
        $last = array_pop($names);

        parent::__construct(\sprintf(
            '%s %s manage encore %s : rattachez %s à un autre manager avant de désactiver son compte.',
            $manager->getFirstName(),
            $manager->getLastName(),
            [] === $names ? $last : implode(', ', $names) . ' et ' . $last,
            [] === $names ? 'cette personne' : 'ces personnes',
        ));
    }
}
