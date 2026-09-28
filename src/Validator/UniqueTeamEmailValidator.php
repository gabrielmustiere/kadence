<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\TeamMemberInput;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UniqueTeamEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueTeamEmail) {
            throw new UnexpectedTypeException($constraint, UniqueTeamEmail::class);
        }

        if (!$value instanceof TeamMemberInput) {
            throw new UnexpectedValueException($value, TeamMemberInput::class);
        }

        if (null === $value->email || '' === $value->email) {
            return;
        }

        $existing = $this->userRepository->findOneByEmail($value->email);
        if (null === $existing || $existing->getId() === $value->id) {
            return;
        }

        $this->context
            ->buildViolation($existing->isActive() ? $constraint->message : $constraint->deactivatedMessage)
            ->atPath('email')
            ->addViolation();
    }
}
