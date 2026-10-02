<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\TeamMemberInput;
use App\Entity\User;
use App\Enum\Type\Role;
use App\Enum\Type\TagCategory;
use App\Exception\LastActiveDirectorException;
use App\Exception\ManagerWithActiveReportsException;
use App\Model\TagLabels;
use App\Model\Week;
use App\Model\WeeklyMaximum;
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
        private WeeklyMaxManager $weeklyMaxManager,
        private TagManager $tagManager,
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

    /**
     * A manager shown is always active: the people already deactivated who name this person lose their manager.
     */
    public function deactivate(User $user): void
    {
        $this->assertIsNotLastActiveDirector($user);

        $reports = $this->userRepository->findReportsOf($user);
        $activeReports = array_values(array_filter($reports, static fn (User $report): bool => $report->isActive()));
        if ([] !== $activeReports) {
            throw new ManagerWithActiveReportsException($user, $activeReports);
        }

        foreach ($reports as $report) {
            $report->setManager(null);
        }
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
            ->setRole($input->role ?? throw new \LogicException('A validated team member input has a role.'))
            ->setHolidayCalendar($input->holidayCalendar ?? throw new \LogicException('A validated team member input has a holiday calendar.'))
            ->replaceTags([
                ...$input->technicalSkills,
                ...$this->tagManager->resolve(TagCategory::TechnicalSkill, TagLabels::split($input->newTechnicalSkills)),
                ...$input->functionalExperiences,
                ...$this->tagManager->resolve(TagCategory::FunctionalExperience, TagLabels::split($input->newFunctionalExperiences)),
                ...(null === $input->teamType ? [] : [$input->teamType]),
                ...$this->tagManager->resolve(TagCategory::TeamType, [$input->newTeamType ?? '']),
            ])
            ->setManager($input->manager);

        $this->weeklyMaxManager->change(
            $user,
            self::weeklyMaxQuarters($input->weeklyMaxDays),
            Week::containing($input->weeklyMaxFrom ?? throw new \LogicException('A validated team member input has a weekly maximum start.')),
        );
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
     * @return int<1, 20>
     */
    private static function weeklyMaxQuarters(?float $days): int
    {
        $quarters = null === $days ? 0 : (int) round($days * 4);
        if ($quarters < 1 || $quarters > WeeklyMaximum::DEFAULT_QUARTERS) {
            throw new \LogicException('A validated team member input has a weekly maximum between a quarter and five days.');
        }

        return $quarters;
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
