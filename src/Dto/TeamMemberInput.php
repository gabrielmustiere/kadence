<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\Role;
use App\Model\Week;
use App\Validator\UniqueTeamEmail;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueTeamEmail]
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

        return $input;
    }
}
