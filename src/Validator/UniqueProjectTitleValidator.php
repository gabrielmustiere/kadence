<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\ProjectInput;
use App\Model\TitleComparison;
use App\Repository\ProjectRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UniqueProjectTitleValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueProjectTitle) {
            throw new UnexpectedTypeException($constraint, UniqueProjectTitle::class);
        }

        if (!$value instanceof ProjectInput) {
            throw new UnexpectedValueException($value, ProjectInput::class);
        }

        if (null === $value->title || '' === $value->title) {
            return;
        }

        if (TitleComparison::isTaken($value->title, $this->projectRepository->findTitlesExcept($value->id))) {
            $this->context->buildViolation($constraint->message)->atPath('title')->addViolation();
        }
    }
}
