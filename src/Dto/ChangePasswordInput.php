<?php

declare(strict_types=1);

namespace App\Dto;

use App\Validator\NotCurrentPassword;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

final class ChangePasswordInput
{
    public const string VOLUNTARY = 'voluntary';

    #[UserPassword(message: 'Le mot de passe actuel est incorrect.', groups: [self::VOLUNTARY])]
    public ?string $currentPassword = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 12, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
    #[Assert\PasswordStrength(message: 'Ce mot de passe est trop prévisible : allongez-le ou variez davantage les caractères.')]
    #[NotCurrentPassword]
    public ?string $newPassword = null;
}
