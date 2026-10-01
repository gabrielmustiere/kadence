<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\TeamMemberInput;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Climbs the chain of managers above the chosen one: meeting the person edited means they would end up managing
 * themselves.
 */
final class NoManagementCycleValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoManagementCycle) {
            throw new UnexpectedTypeException($constraint, NoManagementCycle::class);
        }

        if (!$value instanceof TeamMemberInput) {
            throw new UnexpectedValueException($value, TeamMemberInput::class);
        }

        if (null === $value->id || null === $value->manager) {
            return;
        }

        $visited = [];
        for ($above = $value->manager; null !== $above && !isset($visited[spl_object_id($above)]); $above = $above->getManager()) {
            if ($above->getId() === $value->id) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ manager }}', \sprintf('%s %s', $value->manager->getFirstName(), $value->manager->getLastName()))
                    ->atPath('manager')
                    ->addViolation();

                return;
            }
            $visited[spl_object_id($above)] = true;
        }
    }
}
