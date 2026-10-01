<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\Role;
use App\Enum\Type\TagCategory;
use App\Model\Week;
use App\Validator\NoManagementCycle;
use App\Validator\TagLabelList;
use App\Validator\UniqueTeamEmail;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueTeamEmail]
#[NoManagementCycle]
final class TeamMemberInput
{
    public ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\NotNull]
    public ?Role $role = Role::Prod;

    #[Assert\NotNull]
    public ?HolidayCalendar $holidayCalendar = HolidayCalendar::France;

    #[Assert\NotNull]
    #[Assert\Range(min: 0.25, max: 5)]
    #[Assert\DivisibleBy(value: 0.25, message: 'Indiquez un nombre de jours au quart de journée près (ex. 4,5 ou 4,75).')]
    public ?float $weeklyMaxDays = 5.0;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $weeklyMaxFrom = null;

    /** @var list<Tag> */
    public array $technicalSkills = [];

    #[TagLabelList]
    public ?string $newTechnicalSkills = null;

    /** @var list<Tag> */
    public array $functionalExperiences = [];

    #[TagLabelList]
    public ?string $newFunctionalExperiences = null;

    public ?Tag $teamType = null;

    #[Assert\Length(max: TagLabelList::MAX_LENGTH, normalizer: 'trim')]
    public ?string $newTeamType = null;

    public ?User $manager = null;

    #[Assert\IsTrue(message: 'Choisissez un type d\'équipe existant ou saisissez-en un nouveau, pas les deux.')]
    public function isTeamTypeUnambiguous(): bool
    {
        return null === $this->teamType || '' === trim($this->newTeamType ?? '');
    }

    public static function forNewMember(Week $currentWeek): self
    {
        $input = new self();
        $input->weeklyMaxFrom = $currentWeek->monday;

        return $input;
    }

    /**
     * @param int<1, 20> $weeklyMaxQuarters the maximum in effect for the current week
     */
    public static function fromUser(User $user, int $weeklyMaxQuarters, Week $currentWeek): self
    {
        $input = new self();
        $input->id = $user->getId();
        $input->firstName = $user->getFirstName();
        $input->lastName = $user->getLastName();
        $input->email = $user->getEmail();
        $input->role = $user->getRole();
        $input->holidayCalendar = $user->getHolidayCalendar();
        $input->weeklyMaxDays = $weeklyMaxQuarters / 4;
        $input->weeklyMaxFrom = $currentWeek->monday;
        $input->technicalSkills = $user->tagsOf(TagCategory::TechnicalSkill);
        $input->functionalExperiences = $user->tagsOf(TagCategory::FunctionalExperience);
        $input->teamType = $user->teamType();
        $input->manager = $user->getManager();

        return $input;
    }
}
