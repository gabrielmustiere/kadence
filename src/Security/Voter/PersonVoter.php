<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * What the page of a person shows beyond what everyone sees: their role, tags and load to the leads and the direction,
 * their manager to the direction; both to the person themselves.
 *
 * @extends Voter<string, User>
 */
final class PersonVoter extends Voter
{
    public const string PLANNING = 'PERSON_PLANNING';
    public const string MANAGER = 'PERSON_MANAGER';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::PLANNING, self::MANAGER], true) && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if ($user instanceof User && $user->getId() === $subject->getId()) {
            return true;
        }

        return $this->accessDecisionManager->decide($token, [self::PLANNING === $attribute ? 'ROLE_LEAD' : 'ROLE_DIRECTION']);
    }
}
