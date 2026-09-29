<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\LotInput;
use App\Repository\LotRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UniqueLotTitleValidator extends ConstraintValidator
{
    public function __construct(
        private readonly LotRepository $lotRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueLotTitle) {
            throw new UnexpectedTypeException($constraint, UniqueLotTitle::class);
        }

        if (!$value instanceof LotInput) {
            throw new UnexpectedValueException($value, LotInput::class);
        }

        if (null === $value->project || null === $value->title || '' === $value->title) {
            return;
        }

        $siblingTitles = $this->lotRepository->findSiblingTitles($value->project, $value->parent, $value->id);
        if (TitleComparison::isTaken($value->title, $siblingTitles)) {
            $this->context
                ->buildViolation(null === $value->parent ? $constraint->lotMessage : $constraint->subLotMessage)
                ->atPath('title')
                ->addViolation();
        }
    }
}
