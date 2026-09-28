<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\TeamMemberInput;
use App\Entity\User;
use App\Enum\Type\Role;
use App\Exception\LastActiveDirectorException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class TeamManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private TemporaryPasswordGenerator $temporaryPasswordGenerator,
    ) {
    }

    /**
     * @return array{User, string} the registered user and its temporary password, to be shown once
     */
    public function register(TeamMemberInput $input): array
    {
        $user = new User();
        $this->apply($user, $input);
        $temporaryPassword = $this->issueTemporaryPassword($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [$user, $temporaryPassword];
    }

    public function update(User $user, TeamMemberInput $input): void
    {
        if (Role::Direction !== $input->role) {
            $this->assertIsNotLastActiveDirector($user);
        }

        $this->apply($user, $input);
        $this->entityManager->flush();
    }

    public function deactivate(User $user): void
    {
        $this->assertIsNotLastActiveDirector($user);

        $user->setActive(false);
        $this->entityManager->flush();
    }

    public function reactivate(User $user): void
    {
        $user->setActive(true);
        $this->entityManager->flush();
    }

    public function resetPassword(User $user): string
    {
        $temporaryPassword = $this->issueTemporaryPassword($user);
        $this->entityManager->flush();

        return $temporaryPassword;
    }

    public function changePassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setMustChangePassword(false);
        $this->entityManager->flush();
    }

    private function apply(User $user, TeamMemberInput $input): void
    {
        $user
            ->setFirstName(self::required($input->firstName))
            ->setLastName(self::required($input->lastName))
            ->setEmail(self::required($input->email))
            ->setRole($input->role ?? throw new \LogicException('A validated team member input has a role.'));
    }

    private function issueTemporaryPassword(User $user): string
    {
        $temporaryPassword = $this->temporaryPasswordGenerator->generate();
        $user->setPassword($this->passwordHasher->hashPassword($user, $temporaryPassword));
        $user->setMustChangePassword(true);

        return $temporaryPassword;
    }

    private function assertIsNotLastActiveDirector(User $user): void
    {
        if ($user->isActive() && Role::Direction === $user->getRole() && $this->userRepository->countActiveDirectors() <= 1) {
            throw new LastActiveDirectorException();
        }
    }

    /**
     * @return non-empty-string
     */
    private static function required(?string $value): string
    {
        if (null === $value || '' === $value) {
            throw new \LogicException('A validated team member input has no blank field.');
        }

        return $value;
    }
}
