<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Lot;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Lot>
 */
final class LotVoter extends Voter
{
    public const string EDIT = 'LOT_EDIT';
    public const string PROGRESS = 'LOT_PROGRESS';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::PROGRESS], true) && $subject instanceof Lot;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (self::PROGRESS === $attribute && (!$subject->isLeaf() || null === $subject->getEstimateDays())) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_LEAD'])) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive() || !$subject->isLeaf()) {
            return false;
        }

        $ownerId = $subject->getOwner()?->getId();

        return null !== $ownerId && $ownerId === $user->getId();
    }
}
