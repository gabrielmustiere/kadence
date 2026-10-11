<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\Type\Role;
use App\Repository\UserRepository;
use App\Service\TimeOffApprovers;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TimeOffApproversTest extends KernelTestCase
{
    use CreatesUsers;

    public function testARequestGoesToTheManager(): void
    {
        $manager = $this->createUser(Role::Lead);
        $requester = $this->createUser()->setManager($manager);

        self::assertSame([$manager], $this->approvers()->of($requester));
    }

    public function testARequestWithoutManagerGoesToTheOtherActiveDirectors(): void
    {
        $director = $this->createUser(Role::Direction);
        $other = $this->createUser(Role::Direction);
        $former = $this->createUser(Role::Direction)->setActive(false);
        $this->entityManager()->flush();

        $approvers = $this->approvers()->of($director);

        self::assertContains($other, $approvers);
        self::assertNotContains($director, $approvers);
        self::assertNotContains($former, $approvers);
    }

    public function testTheOnlyActiveDirectorWithoutManagerHasNoApprover(): void
    {
        $director = $this->createUser(Role::Direction);
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findActiveDirectors')->willReturn([$director]);

        self::assertSame([], new TimeOffApprovers($userRepository)->of($director));
    }

    public function testTheManagerAndTheDirectionDecideButNeverThePersonNorAnotherLead(): void
    {
        $manager = $this->createUser(Role::Lead);
        $requester = $this->createUser()->setManager($manager);

        self::assertTrue(TimeOffApprovers::canDecideFor($manager, $requester));
        self::assertTrue(TimeOffApprovers::canDecideFor($this->createUser(Role::Direction), $requester));
        self::assertFalse(TimeOffApprovers::canDecideFor($this->createUser(Role::Lead), $requester));
        self::assertFalse(TimeOffApprovers::canDecideFor($requester, $requester));
        self::assertFalse(TimeOffApprovers::canDecideFor($manager, $manager));
    }

    public function testTheDetailIsShownToThePersonTheirManagerAndTheDirection(): void
    {
        $manager = $this->createUser(Role::Lead);
        $person = $this->createUser()->setManager($manager);

        self::assertTrue(TimeOffApprovers::canSeeDetailOf($person, $person));
        self::assertTrue(TimeOffApprovers::canSeeDetailOf($manager, $person));
        self::assertTrue(TimeOffApprovers::canSeeDetailOf($this->createUser(Role::Direction), $person));
        self::assertFalse(TimeOffApprovers::canSeeDetailOf($this->createUser(), $person));
    }

    public function testTheDirectionAndTheManagersOfActivePeopleHaveAnInbox(): void
    {
        $manager = $this->createUser(Role::Prod);
        $this->createUser()->setManager($manager);
        $this->entityManager()->flush();

        self::assertTrue($this->approvers()->hasInbox($manager));
        self::assertTrue($this->approvers()->hasInbox($this->createUser(Role::Direction)));
        self::assertFalse($this->approvers()->hasInbox($this->createUser(Role::Lead)));
    }

    private function approvers(): TimeOffApprovers
    {
        $approvers = static::getContainer()->get(TimeOffApprovers::class);
        \assert($approvers instanceof TimeOffApprovers);

        return $approvers;
    }
}
