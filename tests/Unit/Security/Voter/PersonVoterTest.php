<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use App\Security\Voter\PersonVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PersonVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, string, int}>
     */
    public static function votes(): iterable
    {
        yield 'direction sees the planning of anyone' => [['ROLE_DIRECTION', 'ROLE_LEAD'], PersonVoter::PLANNING, VoterInterface::ACCESS_GRANTED];
        yield 'direction sees the manager of anyone' => [['ROLE_DIRECTION', 'ROLE_LEAD'], PersonVoter::MANAGER, VoterInterface::ACCESS_GRANTED];
        yield 'a lead sees the planning of anyone' => [['ROLE_LEAD'], PersonVoter::PLANNING, VoterInterface::ACCESS_GRANTED];
        yield 'a lead does not see the manager of someone else' => [['ROLE_LEAD'], PersonVoter::MANAGER, VoterInterface::ACCESS_DENIED];
        yield 'prod does not see the planning of someone else' => [[], PersonVoter::PLANNING, VoterInterface::ACCESS_DENIED];
        yield 'prod does not see the manager of someone else' => [[], PersonVoter::MANAGER, VoterInterface::ACCESS_DENIED];
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('votes')]
    public function testWhatEachRoleSeesOfSomeoneElse(array $roles, string $attribute, int $expected): void
    {
        self::assertSame($expected, $this->vote($this->user(1), $this->user(2), $attribute, $roles));
    }

    public function testEveryoneSeesTheirOwnPlanningAndManager(): void
    {
        $person = $this->user(1);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($person, $person, PersonVoter::PLANNING, []));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($person, $person, PersonVoter::MANAGER, []));
    }

    public function testAbstainsOnAnotherAttributeOrSubject(): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($this->user(1), $this->user(2), 'LOT_EDIT', ['ROLE_LEAD']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($this->user(1), new Project(), PersonVoter::PLANNING, ['ROLE_LEAD']));
    }

    /**
     * @param list<string> $roles
     */
    private function vote(User $viewer, object $subject, string $attribute, array $roles): int
    {
        $accessDecisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $accessDecisionManager->method('decide')->willReturnCallback(static fn (TokenInterface $token, array $attributes): bool => \in_array($attributes[0], $roles, true));
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($viewer);

        return new PersonVoter($accessDecisionManager)->vote($token, $subject, [$attribute]);
    }

    private function user(int $id): User
    {
        $user = new User()->setFirstName('Paula')->setLastName('Durand')->setEmail('paula' . $id . '@example.com');
        new \ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
