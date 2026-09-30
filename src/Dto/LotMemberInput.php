<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\LotMember;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

final class LotMemberInput
{
    #[Assert\NotNull(message: 'Choisissez une personne.')]
    public ?User $user = null;

    #[Assert\NotNull(message: 'Choisissez une part.')]
    #[Assert\Choice(choices: LotMember::SHARES, message: 'Choisissez une part de 25, 50, 75 ou 100 %.')]
    public ?int $share = 100;

    /**
     * @param int<25, 100> $share
     */
    public static function of(User $user, int $share): self
    {
        $input = new self();
        $input->user = $user;
        $input->share = $share;

        return $input;
    }

    /**
     * Uniqueness key of a team row: the person, or the row itself while no person is chosen.
     */
    public static function identify(mixed $row): string
    {
        if (!$row instanceof self || null === $row->user) {
            return \is_object($row) ? 'row-' . spl_object_id($row) : 'row';
        }

        return 'user-' . $row->user->getId();
    }
}
