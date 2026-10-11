<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\Type\Role;
use App\Repository\UserRepository;

/**
 * Who decides on the time off of a person: their manager, and the direction at any time in their stead; never the
 * person themselves.
 */
final readonly class TimeOffApprovers
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    /**
     * The people a new request of this person is sent to: their active manager, else the other active directors. None
     * for the only active director without a manager, whose requests are approved on their own.
     *
     * @return list<User>
     */
    public function of(User $requester): array
    {
        $manager = $requester->getManager();
        if (null !== $manager && $manager->isActive() && $manager->getId() !== $requester->getId()) {
            return [$manager];
        }

        return array_values(array_filter(
            $this->userRepository->findActiveDirectors(),
            static fn (User $director): bool => $director->getId() !== $requester->getId(),
        ));
    }

    public static function canDecideFor(User $viewer, User $requester): bool
    {
        if ($viewer->getId() === $requester->getId() || !$viewer->isActive()) {
            return false;
        }

        return Role::Direction === $viewer->getRole() || $requester->getManager()?->getId() === $viewer->getId();
    }

    /**
     * The type, comment and pending requests of a person are shown to them, their manager and the direction only.
     */
    public static function canSeeDetailOf(User $viewer, User $person): bool
    {
        return $viewer->getId() === $person->getId() || self::canDecideFor($viewer, $person);
    }

    /**
     * The direction, and anyone who manages an active person, have requests to decide on.
     */
    public function hasInbox(User $viewer): bool
    {
        return Role::Direction === $viewer->getRole() || $this->userRepository->hasActiveReports($viewer);
    }
}
