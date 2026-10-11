<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\HalfDay;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffType;
use App\Repository\UserRepository;
use App\Security\Voter\TimeOffVoter;
use App\Service\TimeOffApprovers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class TimeOffVoterTest extends TestCase
{
    private User $manager;
    private User $requester;

    protected function setUp(): void
    {
        $this->manager = self::user(1, Role::Lead);
        $this->requester = self::user(2, Role::Prod)->setManager($this->manager);
    }

    public function testTheManagerAndTheDirectionDecideOnAPendingRequest(): void
    {
        $request = $this->request('2026-11-16');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->manager, TimeOffVoter::DECIDE, $request));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(self::user(3, Role::Direction), TimeOffVoter::DECIDE, $request));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(self::user(4, Role::Lead), TimeOffVoter::DECIDE, $request));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->requester, TimeOffVoter::DECIDE, $request));
    }

    public function testNobodyDecidesOnARequestDecidedAlready(): void
    {
        $request = $this->request('2026-11-16')->approve($this->manager, new \DateTimeImmutable());

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->manager, TimeOffVoter::DECIDE, $request));
    }

    public function testThePersonCancelsAPendingRequestAtAnyTime(): void
    {
        $request = $this->request('2026-10-05');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->requester, TimeOffVoter::CANCEL, $request));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->manager, TimeOffVoter::CANCEL, $request));
    }

    public function testThePersonCancelsApprovedTimeOffUntilItsFirstDay(): void
    {
        $tomorrow = $this->request('2026-10-13')->approve($this->manager, new \DateTimeImmutable());
        $today = $this->request('2026-10-12')->approve($this->manager, new \DateTimeImmutable());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->requester, TimeOffVoter::CANCEL, $tomorrow));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->requester, TimeOffVoter::CANCEL, $today));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->manager, TimeOffVoter::CANCEL, $tomorrow));
    }

    public function testTheDirectionCancelsApprovedTimeOffAtAnyTime(): void
    {
        $started = $this->request('2026-10-05')->approve($this->manager, new \DateTimeImmutable());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(self::user(3, Role::Direction), TimeOffVoter::CANCEL, $started));
    }

    public function testRefusedOrCancelledRequestsAreNeverCancelled(): void
    {
        $refused = $this->request('2026-11-16')->refuse($this->manager, new \DateTimeImmutable(), 'Non');

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->requester, TimeOffVoter::CANCEL, $refused));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(self::user(3, Role::Direction), TimeOffVoter::CANCEL, $refused));
    }

    public function testTheDetailShowsToThePersonTheirManagerAndTheDirection(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->requester, TimeOffVoter::DETAIL, $this->requester));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->manager, TimeOffVoter::DETAIL, $this->requester));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(self::user(3, Role::Direction), TimeOffVoter::DETAIL, $this->requester));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(self::user(4, Role::Lead), TimeOffVoter::DETAIL, $this->requester));
    }

    private function vote(User $user, string $attribute, TimeOffRequest|User $subject): int
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $voter = new TimeOffVoter(new TimeOffApprovers($this->createStub(UserRepository::class)), new MockClock('2026-10-12 10:00'));

        return $voter->vote($token, $subject, [$attribute]);
    }

    private function request(string $start): TimeOffRequest
    {
        return new TimeOffRequest($this->requester, TimeOffType::PaidLeave, new \DateTimeImmutable($start), HalfDay::Morning, new \DateTimeImmutable($start)->modify('+4 days'), HalfDay::Afternoon, null, new \DateTimeImmutable('2026-10-01'));
    }

    private static function user(int $id, Role $role): User
    {
        $user = new User()->setFirstName('Test')->setLastName('User')->setEmail('user' . $id . '@example.com')->setRole($role);
        new \ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
