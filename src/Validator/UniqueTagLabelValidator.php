<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\TagInput;
use App\Model\TitleComparison;
use App\Repository\TagRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UniqueTagLabelValidator extends ConstraintValidator
{
    public function __construct(
        private readonly TagRepository $tagRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueTagLabel) {
            throw new UnexpectedTypeException($constraint, UniqueTagLabel::class);
        }

        if (!$value instanceof TagInput) {
            throw new UnexpectedValueException($value, TagInput::class);
        }

        if (null === $value->category || null === $value->label || '' === trim($value->label)) {
            return;
        }

        if (TitleComparison::isTaken($value->label, $this->tagRepository->findLabelsExcept($value->category, $value->id))) {
            $this->context->buildViolation($constraint->message)->atPath('label')->addViolation();
        }
    }
}
