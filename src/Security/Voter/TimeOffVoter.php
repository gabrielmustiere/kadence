<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Service\TimeOffApprovers;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Deciding on a pending request belongs to the manager of its person and to the direction, never to the person.
 * The person cancels a pending request, or approved time off until its first day; the direction cancels approved
 * time off at any time. The type of the time off of a person shows to them, their manager and the direction.
 *
 * @extends Voter<string, TimeOffRequest|User|null>
 */
final class TimeOffVoter extends Voter
{
    public const string DECIDE = 'TIME_OFF_DECIDE';
    public const string CANCEL = 'TIME_OFF_CANCEL';
    public const string DETAIL = 'TIME_OFF_DETAIL';
    public const string INBOX = 'TIME_OFF_INBOX';

    public function __construct(
        private readonly TimeOffApprovers $timeOffApprovers,
        private readonly ClockInterface $clock,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::DECIDE, self::CANCEL => $subject instanceof TimeOffRequest,
            self::DETAIL => $subject instanceof User,
            self::INBOX => null === $subject,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match (true) {
            $subject instanceof TimeOffRequest && self::DECIDE === $attribute => TimeOffStatus::Pending === $subject->getStatus() && TimeOffApprovers::canDecideFor($user, $subject->getUser()),
            $subject instanceof TimeOffRequest => $this->canCancel($user, $subject),
            $subject instanceof User => TimeOffApprovers::canSeeDetailOf($user, $subject),
            default => $this->timeOffApprovers->hasInbox($user),
        };
    }

    private function canCancel(User $user, TimeOffRequest $request): bool
    {
        $own = $user->getId() === $request->getUser()->getId();

        return match ($request->getStatus()) {
            TimeOffStatus::Pending => $own,
            TimeOffStatus::Approved => ($own && $request->getStartDay() > $this->clock->now()->setTime(0, 0)) || Role::Direction === $user->getRole(),
            default => false,
        };
    }
}
