<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use App\Enum\Type\Role;
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

    public static function fromUser(User $user): self
    {
        $input = new self();
        $input->id = $user->getId();
        $input->firstName = $user->getFirstName();
        $input->lastName = $user->getLastName();
        $input->email = $user->getEmail();
        $input->role = $user->getRole();

        return $input;
    }
}
