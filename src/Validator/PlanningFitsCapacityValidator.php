<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\LotInput;
use App\Model\Quarters;
use App\Model\Schedule\LeafPlan;
use App\Model\Schedule\ScheduleData;
use App\Model\Schedule\ScheduleResult;
use App\Service\ScheduleLoader;
use App\Service\Scheduler;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Refuses a planning change (start date, team, shares, owner) that would load someone beyond a full load on a day
 * where they were not, or more than they already were. Both sides are worked out with the new estimate, so that
 * revising the estimate alone is never refused.
 */
final class PlanningFitsCapacityValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ScheduleLoader $scheduleLoader,
        private readonly Scheduler $scheduler,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PlanningFitsCapacity) {
            throw new UnexpectedTypeException($constraint, PlanningFitsCapacity::class);
        }
        if (!$value instanceof LotInput) {
            throw new UnexpectedValueException($value, LotInput::class);
        }

        $members = ScheduleLoader::plannedMembers($value->effectiveMembers());
        if (!$value->planningChanged() || null === $value->startDate || [] === $members) {
            return;
        }

        $data = $this->scheduleLoader->load($value->startDate);
        $replacedId = $this->replacedLeafId($value, $data);
        if (false === $replacedId) {
            return;
        }

        $current = null === $replacedId ? new LeafPlan(0, null, 0, null, null, null, []) : $data->plans[$replacedId];
        $estimate = null === $value->estimateDays ? null : $value->estimateDays * Quarters::PER_DAY;
        $before = $current->withPlanning($estimate, $current->startDate, $current->members);
        $after = $current->withPlanning($estimate, $value->startDate, $members);

        $otherPlans = $data->plans;
        if (null !== $replacedId) {
            unset($otherPlans[$replacedId]);
        }
        $others = $this->scheduler->schedule(array_values($otherPlans), $data->capacity, $data->today);
        $overload = $this->firstOverload($others, $this->loadOf($before, $data), $this->loadOf($after, $data));
        if (null !== $overload) {
            $this->violation($constraint, $data, $others, ...$overload);
        }
    }

    /**
     * The leaf whose planning the input replaces: the edited leaf, the leaf a first sub-lot takes over, or none for a
     * new leaf; false when the input does not plan a leaf.
     */
    private function replacedLeafId(LotInput $input, ScheduleData $data): int|false|null
    {
        if (null !== $input->id) {
            return isset($data->plans[$input->id]) ? $input->id : false;
        }

        $parentId = $input->parent?->getId();

        return null !== $parentId && isset($data->plans[$parentId]) ? $parentId : null;
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function loadOf(LeafPlan $plan, ScheduleData $data): array
    {
        return $this->scheduler->loadOf($plan, $this->scheduler->scheduleLeaf($plan, $data->capacity, $data->today), $data->capacity);
    }

    /**
     * @param array<int, array<string, int>> $before
     * @param array<int, array<string, int>> $after
     *
     * @return array{int, string, int}|null user id, day (Y-m-d) and load of the earliest overload the change creates or worsens
     */
    private function firstOverload(ScheduleResult $others, array $before, array $after): ?array
    {
        $first = null;
        foreach ($after as $userId => $days) {
            foreach ($days as $day => $share) {
                $load = $others->loadAt($userId, $day) + $share;
                $previous = $others->loadAt($userId, $day) + ($before[$userId][$day] ?? 0);
                if ($load > ScheduleResult::FULL_LOAD && $load > $previous && (null === $first || $day < $first[1])) {
                    $first = [$userId, $day, $load];
                }
            }
        }

        return $first;
    }

    private function violation(PlanningFitsCapacity $constraint, ScheduleData $data, ScheduleResult $others, int $userId, string $day, int $load): void
    {
        $person = $data->people[$userId] ?? null;
        $leaf = $data->leaves[$others->contributorsAt($userId, $day)[0] ?? 0] ?? null;

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ person }}', null === $person ? '' : \sprintf('%s %s', $person->getFirstName(), $person->getLastName()))
            ->setParameter('{{ load }}', (string) $load)
            ->setParameter('{{ day }}', new \DateTimeImmutable($day)->format('d/m/Y'))
            ->setParameter('{{ leaf }}', (string) $leaf?->getTitle())
            ->setParameter('{{ project }}', (string) $leaf?->getProject()->getTitle())
            ->atPath('members')
            ->addViolation();
    }
}
