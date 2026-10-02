<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Security\Voter\LotVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class LotVoterTest extends TestCase
{
    public function testLeadMayEditAnyLot(): void
    {
        $lot = $this->splitLot();

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->user(1), $lot, isLead: true));
    }

    public function testActiveOwnerMayEditTheirLeaf(): void
    {
        $owner = $this->user(1);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($owner, $this->leaf($owner)));
    }

    public function testDeactivatedOwnerMayNotEditTheirLeaf(): void
    {
        $owner = $this->user(1)->setActive(false);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($owner, $this->leaf($owner)));
    }

    public function testOtherMemberMayNotEditTheLeaf(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->user(2), $this->leaf($this->user(1))));
    }

    public function testNonLeadMayNotEditASplitLot(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->user(1), $this->splitLot()));
    }

    public function testLeafWithoutOwnerIsLeadOnly(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->user(1), $this->leaf(null)));
    }

    public function testLeadMayDeclareTheProgressOfAnyEstimatedLeaf(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->user(1), $this->leaf(null)->setEstimateDays(5), isLead: true, attribute: LotVoter::PROGRESS));
    }

    public function testActiveOwnerMayDeclareTheProgressOfTheirEstimatedLeaf(): void
    {
        $owner = $this->user(1);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($owner, $this->leaf($owner)->setEstimateDays(5), attribute: LotVoter::PROGRESS));
    }

    public function testOthersMayNotDeclareTheProgressOfALeaf(): void
    {
        $owner = $this->user(1)->setActive(false);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($owner, $this->leaf($owner)->setEstimateDays(5), attribute: LotVoter::PROGRESS));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->user(2), $this->leaf($this->user(1))->setEstimateDays(5), attribute: LotVoter::PROGRESS));
    }

    public function testNobodyDeclaresTheProgressOfASplitLotOrOfALeafToEstimate(): void
    {
        $owner = $this->user(1);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($owner, $this->splitLot(), isLead: true, attribute: LotVoter::PROGRESS));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($owner, $this->leaf($owner), isLead: true, attribute: LotVoter::PROGRESS));
    }

    private function vote(User $user, Lot $lot, bool $isLead = false, string $attribute = LotVoter::EDIT): int
    {
        $accessDecisionManager = $this->createStub(AccessDecisionManagerInterface::class);
        $accessDecisionManager->method('decide')->willReturn($isLead);
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return new LotVoter($accessDecisionManager)->vote($token, $lot, [$attribute]);
    }

    private function leaf(?User $owner): Lot
    {
        return new Lot(new Project()->setTitle('Kadence'))->setTitle('Lot')->setOwner($owner);
    }

    private function splitLot(): Lot
    {
        $lot = new Lot(new Project()->setTitle('Kadence'))->setTitle('Lot');
        new Lot($lot->getProject(), $lot)->setTitle('Sous-lot');

        return $lot;
    }

    private function user(int $id): User
    {
        $user = new User()->setFirstName('Paula')->setLastName('Durand')->setEmail('paula' . $id . '@example.com');
        new \ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
