<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\TeamMemberInput;
use App\Entity\Tag;
use App\Entity\User;
use App\Entity\WeeklyMax;
use App\Enum\Type\Role;
use App\Enum\Type\TagCategory;
use App\Exception\LastActiveDirectorException;
use App\Exception\ManagerWithActiveReportsException;
use App\Model\Week;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Repository\WeeklyMaxRepository;
use App\Service\TagManager;
use App\Service\TeamManager;
use App\Service\TemporaryPasswordGenerator;
use App\Service\WeeklyMaxManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TeamManagerTest extends TestCase
{
    public function testRegisterPersistsMemberWithTemporaryPassword(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with(self::isInstanceOf(User::class));
        $entityManager->expects($this->once())->method('flush');

        [$user, $temporaryPassword] = $this->teamManager($entityManager)->register($this->input(Role::Lead));

        self::assertSame('jeanne.dupont@example.com', $user->getEmail());
        self::assertSame('Jeanne', $user->getFirstName());
        self::assertSame('Dupont', $user->getLastName());
        self::assertSame(Role::Lead, $user->getRole());
        self::assertTrue($user->isActive());
        self::assertTrue($user->mustChangePassword());
        self::assertSame('hashed:' . $temporaryPassword, $user->getPassword());
        self::assertSame(TemporaryPasswordGenerator::LENGTH, \strlen($temporaryPassword));
    }

    public function testRegisterWithAPartTimeMaximumRecordsItFromTheChosenWeek(): void
    {
        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            },
        );
        $entityManager->expects($this->once())->method('flush');
        $input = $this->input(Role::Prod);
        $input->weeklyMaxDays = 4.5;
        $input->weeklyMaxFrom = new \DateTimeImmutable('2026-10-07');

        $this->teamManager($entityManager)->register($input);

        $weeklyMaxes = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof WeeklyMax));
        self::assertCount(1, $weeklyMaxes);
        self::assertSame(18, $weeklyMaxes[0]->getQuarters());
        self::assertSame('2026-10-05', $weeklyMaxes[0]->getEffectiveFrom()->format('Y-m-d'));
    }

    public function testDeactivatingLastActiveDirectorIsRefused(): void
    {
        $director = $this->user(Role::Direction);

        $this->expectException(LastActiveDirectorException::class);

        try {
            $this->teamManager(activeDirectors: 1)->deactivate($director);
        } finally {
            self::assertTrue($director->isActive());
        }
    }

    public function testDeactivatingDirectorIsAllowedWhenAnotherDirectorIsActive(): void
    {
        $director = $this->user(Role::Direction);

        $this->teamManager(activeDirectors: 2)->deactivate($director);

        self::assertFalse($director->isActive());
    }

    public function testDeactivatingNonDirectorIsAllowed(): void
    {
        $prod = $this->user(Role::Prod);

        $this->teamManager(activeDirectors: 1)->deactivate($prod);

        self::assertFalse($prod->isActive());
    }

    public function testDeactivatingSomeoneWhoStillManagesActivePeopleIsRefusedNamingThem(): void
    {
        $manager = $this->user(Role::Lead)->setFirstName('Julien')->setLastName('Moreau');
        $camille = $this->user(Role::Prod)->setFirstName('Camille')->setLastName('Roux')->setManager($manager);
        $thomas = $this->user(Role::Prod)->setFirstName('Thomas')->setLastName('Girard')->setManager($manager);
        $former = $this->user(Role::Prod)->setActive(false)->setManager($manager);

        try {
            $this->teamManager(reports: [$camille, $former, $thomas])->deactivate($manager);
            self::fail('Deactivating a manager of active people is refused.');
        } catch (ManagerWithActiveReportsException $exception) {
            self::assertStringStartsWith('Julien Moreau manage encore Camille Roux et Thomas Girard :', $exception->getMessage());
        }

        self::assertTrue($manager->isActive());
        self::assertSame($manager, $former->getManager());
    }

    public function testDeactivatingAManagerOfDeactivatedPeopleOnlyClearsTheirManager(): void
    {
        $manager = $this->user(Role::Lead);
        $former = $this->user(Role::Prod)->setActive(false)->setManager($manager);

        $this->teamManager(reports: [$former])->deactivate($manager);

        self::assertFalse($manager->isActive());
        self::assertNull($former->getManager());
    }

    public function testUpdateSetsTheTagsTheNewOnesTypedAndTheManager(): void
    {
        $member = $this->user(Role::Prod);
        $symfony = new Tag(TagCategory::TechnicalSkill, 'Symfony');
        $back = new Tag(TagCategory::TeamType, 'Back');
        $manager = $this->user(Role::Lead);
        $input = $this->input(Role::Prod);
        $input->technicalSkills = [$symfony];
        $input->newTechnicalSkills = 'Go';
        $input->newFunctionalExperiences = 'Paie, Facturation';
        $input->teamType = $back;
        $input->manager = $manager;

        $this->teamManager()->update($member, $input);

        self::assertSame(['Go', 'Symfony'], array_map(static fn (Tag $tag): string => $tag->getLabel(), $member->tagsOf(TagCategory::TechnicalSkill)));
        self::assertSame(['Facturation', 'Paie'], array_map(static fn (Tag $tag): string => $tag->getLabel(), $member->tagsOf(TagCategory::FunctionalExperience)));
        self::assertSame($back, $member->teamType());
        self::assertSame($manager, $member->getManager());
    }

    public function testDemotingLastActiveDirectorIsRefused(): void
    {
        $director = $this->user(Role::Direction);

        $this->expectException(LastActiveDirectorException::class);

        try {
            $this->teamManager(activeDirectors: 1)->update($director, $this->input(Role::Lead));
        } finally {
            self::assertSame(Role::Direction, $director->getRole());
        }
    }

    public function testUpdatingLastActiveDirectorWithoutChangingRoleIsAllowed(): void
    {
        $director = $this->user(Role::Direction);

        $this->teamManager(activeDirectors: 1)->update($director, $this->input(Role::Direction));

        self::assertSame('Jeanne', $director->getFirstName());
        self::assertSame(Role::Direction, $director->getRole());
    }

    public function testReactivateKeepsPassword(): void
    {
        $user = $this->user(Role::Prod)->setActive(false);

        $this->teamManager()->reactivate($user);

        self::assertTrue($user->isActive());
        self::assertSame('hashed:original', $user->getPassword());
        self::assertFalse($user->mustChangePassword());
    }

    public function testResetPasswordIssuesNewTemporaryPassword(): void
    {
        $user = $this->user(Role::Prod);

        $temporaryPassword = $this->teamManager()->resetPassword($user);

        self::assertSame('hashed:' . $temporaryPassword, $user->getPassword());
        self::assertTrue($user->mustChangePassword());
    }

    public function testChangePasswordClearsTemporaryFlag(): void
    {
        $user = $this->user(Role::Prod)->setMustChangePassword(true);

        $this->teamManager()->changePassword($user, 'a new long passphrase');

        self::assertSame('hashed:a new long passphrase', $user->getPassword());
        self::assertFalse($user->mustChangePassword());
    }

    /**
     * @param list<User> $reports the people naming as manager whoever is deactivated
     */
    private function teamManager(?EntityManagerInterface $entityManager = null, int $activeDirectors = 1, array $reports = []): TeamManager
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('countActiveDirectors')->willReturn($activeDirectors);
        $userRepository->method('findReportsOf')->willReturn($reports);

        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturnCallback(
            static fn (User $user, string $plainPassword): string => 'hashed:' . $plainPassword,
        );

        $entityManager ??= $this->createStub(EntityManagerInterface::class);

        return new TeamManager(
            $entityManager,
            $userRepository,
            $passwordHasher,
            new TemporaryPasswordGenerator(),
            new WeeklyMaxManager($entityManager, $this->createStub(WeeklyMaxRepository::class)),
            new TagManager($entityManager, $this->createStub(TagRepository::class), $userRepository),
        );
    }

    private function user(Role $role): User
    {
        return new User()
            ->setEmail('member@example.com')
            ->setFirstName('Member')
            ->setLastName('Test')
            ->setRole($role)
            ->setPassword('hashed:original');
    }

    private function input(Role $role): TeamMemberInput
    {
        $input = new TeamMemberInput();
        $input->firstName = 'Jeanne';
        $input->lastName = 'Dupont';
        $input->email = 'Jeanne.Dupont@example.com';
        $input->role = $role;
        $input->weeklyMaxFrom = Week::containing(new \DateTimeImmutable('2026-09-30'))->monday;

        return $input;
    }
}
