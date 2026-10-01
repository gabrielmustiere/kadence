<?php

declare(strict_types=1);

namespace App\Validator;

use App\Service\TagManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class TagLabelListValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TagLabelList) {
            throw new UnexpectedTypeException($constraint, TagLabelList::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        foreach (TagManager::split($value) as $label) {
            if (mb_strlen($label) > TagLabelList::MAX_LENGTH) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ label }}', $label)
                    ->setParameter('{{ limit }}', (string) TagLabelList::MAX_LENGTH)
                    ->addViolation();

                return;
            }
        }
    }
}
