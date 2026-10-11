<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\TimeOffRequestInput;
use App\Entity\User;
use App\Enum\Type\HalfDay;
use App\Model\Quarters;
use App\Model\TimeOff\TimeOffDays;
use App\Repository\TimeEntryRepository;
use App\Service\TimeOffBook;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * A request covers at least one working half-day, none already taken by another pending or approved request, and no
 * day on which more time is entered than the time off leaves: a lock never sits on time entered.
 */
final class RequestableTimeOffValidator extends ConstraintValidator
{
    public function __construct(
        private readonly TimeOffBook $timeOffBook,
        private readonly TimeEntryRepository $timeEntryRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RequestableTimeOff) {
            throw new UnexpectedTypeException($constraint, RequestableTimeOff::class);
        }

        if (!$value instanceof TimeOffRequestInput) {
            throw new UnexpectedValueException($value, TimeOffRequestInput::class);
        }

        $startDay = $value->startDay;
        $endDay = $value->endDay;
        if (null === $startDay || null === $value->startHalf || null === $endDay || null === $value->endHalf) {
            return;
        }

        $start = $startDay->format('Y-m-d');
        $end = $endDay->format('Y-m-d');
        if ($end < $start || ($end === $start && HalfDay::Afternoon === $value->startHalf && HalfDay::Morning === $value->endHalf)) {
            $this->context->buildViolation($constraint->endBeforeStartMessage)->atPath('endDay')->addViolation();

            return;
        }

        $days = $this->timeOffBook->requestedDays($value->requester, $startDay, $value->startHalf, $endDay, $value->endHalf);
        if ($days->isEmpty()) {
            $this->context->buildViolation($constraint->noWorkingDayMessage)->atPath('startDay')->addViolation();

            return;
        }

        $this->checkOverlap($value->requester, $startDay, $endDay, $days, $constraint);
        $this->checkTimeEntered($value->requester, $startDay, $endDay, $days, $constraint);
    }

    private function checkOverlap(User $requester, \DateTimeImmutable $startDay, \DateTimeImmutable $endDay, TimeOffDays $days, RequestableTimeOff $constraint): void
    {
        foreach ($this->timeOffBook->daysOf($requester, $startDay, $endDay) as $day => $taken) {
            foreach ($taken->halves as [$half]) {
                if (\in_array($half, $days->halves[$day] ?? [], true)) {
                    $this->context
                        ->buildViolation($constraint->overlapMessage)
                        ->setParameter('{{ day }}', new \DateTimeImmutable($day)->format('d/m'))
                        ->setParameter('{{ half }}', $half->label())
                        ->atPath('startDay')
                        ->addViolation();

                    return;
                }
            }
        }
    }

    private function checkTimeEntered(User $requester, \DateTimeImmutable $startDay, \DateTimeImmutable $endDay, TimeOffDays $days, RequestableTimeOff $constraint): void
    {
        $entered = [];
        foreach ($this->timeEntryRepository->findForUserBetween($requester, $startDay, $endDay) as $entry) {
            $day = $entry->getDay()->format('Y-m-d');
            $entered[$day] = ($entered[$day] ?? 0) + $entry->getQuarters();
        }

        foreach ($days->halves as $day => $halves) {
            $quarters = $entered[$day] ?? 0;
            if ($quarters > Quarters::PER_DAY - \count($halves) * Quarters::PER_DAY / 2) {
                $this->context
                    ->buildViolation($constraint->timeEnteredMessage)
                    ->setParameter('{{ entered }}', Quarters::toDays($quarters))
                    ->setParameter('{{ day }}', new \DateTimeImmutable($day)->format('d/m'))
                    ->atPath('startDay')
                    ->addViolation();

                return;
            }
        }
    }
}
