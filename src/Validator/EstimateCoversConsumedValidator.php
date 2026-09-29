<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\LotInput;
use App\Model\Quarters;
use App\Repository\TimeEntryRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class EstimateCoversConsumedValidator extends ConstraintValidator
{
    public function __construct(
        private readonly TimeEntryRepository $timeEntryRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EstimateCoversConsumed) {
            throw new UnexpectedTypeException($constraint, EstimateCoversConsumed::class);
        }

        if (!$value instanceof LotInput) {
            throw new UnexpectedValueException($value, LotInput::class);
        }

        // A leaf may overrun its estimate: only a revision is bounded by the consumed time.
        if ($value->estimateDays === $value->currentEstimateDays) {
            return;
        }

        $consumed = $this->consumedQuarters($value);
        if (0 === $consumed) {
            return;
        }

        if (null === $value->estimateDays) {
            $this->violation($constraint->removedMessage, $consumed);

            return;
        }

        if ($value->estimateDays * Quarters::PER_DAY < $consumed) {
            $this->violation($constraint->belowMessage, $consumed);
        }
    }

    /**
     * Time entered on the edited leaf, or on the leaf a first sub-lot takes over.
     */
    private function consumedQuarters(LotInput $input): int
    {
        if (null !== $input->id) {
            return $this->timeEntryRepository->sumQuartersForLotId($input->id);
        }

        $parent = $input->parent;
        $parentId = $parent?->getId();
        if (null === $parent || null === $parentId || !$parent->isLeaf()) {
            return 0;
        }

        return $this->timeEntryRepository->sumQuartersForLotId($parentId);
    }

    private function violation(string $message, int $consumed): void
    {
        $this->context->buildViolation($message)
            ->setParameter('{{ consumed }}', Quarters::toDays($consumed))
            ->setParameter('{{ minimum }}', (string) Quarters::daysRoundedUp($consumed))
            ->atPath('estimateDays')
            ->addViolation();
    }
}
