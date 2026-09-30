<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\HolidayAdditionInput;
use App\Service\HolidayManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class AddableHolidayValidator extends ConstraintValidator
{
    public function __construct(
        private readonly HolidayManager $holidayManager,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AddableHoliday) {
            throw new UnexpectedTypeException($constraint, AddableHoliday::class);
        }

        if (!$value instanceof HolidayAdditionInput) {
            throw new UnexpectedValueException($value, HolidayAdditionInput::class);
        }

        if (null === $value->calendar || null === $value->day) {
            return;
        }

        $message = match (true) {
            (int) $value->day->format('N') > 5 => $constraint->weekendMessage,
            false === $this->holidayManager->adjustmentAt($value->calendar, $value->day)?->isAdded() => $constraint->removedMessage,
            $this->holidayManager->isHoliday($value->calendar, $value->day) => $constraint->alreadyHolidayMessage,
            default => null,
        };
        if (null === $message) {
            return;
        }

        $this->context
            ->buildViolation($message)
            ->setParameter('{{ day }}', $value->day->format('d/m/Y'))
            ->setParameter('{{ calendar }}', $value->calendar->label())
            ->atPath('day')
            ->addViolation();
    }
}
