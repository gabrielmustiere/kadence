<?php

declare(strict_types=1);

namespace App\Model\TimeOff;

use App\Entity\TimeOffRequest;
use App\Enum\Type\TimeOffStatus;

/**
 * What is left of the entitlements of a period, in half-days: it goes below 0 when the requests exceed them.
 */
final readonly class Balance
{
    public function __construct(
        public TimeOffPeriod $period,
        public int $entitledHalfDays,
        public int $approvedHalfDays,
        public int $pendingHalfDays,
    ) {
    }

    /**
     * The balance of a period, from the requests of one person: those of the type of the period count their half-days
     * within it, on the working days of the person.
     *
     * @param iterable<TimeOffRequest> $requests the requests of the person
     * @param array<string, string>    $holidays the holidays of the person over the period, by day (Y-m-d)
     */
    public static function count(TimeOffPeriod $period, int $entitledHalfDays, iterable $requests, array $holidays): self
    {
        $counted = [TimeOffStatus::Approved->value => 0, TimeOffStatus::Pending->value => 0];
        foreach ($requests as $request) {
            $status = $request->getStatus()->value;
            if ($request->getType() === $period->type && isset($counted[$status])) {
                $counted[$status] += TimeOffDays::of($request, $holidays)->halfDayCountIn($period);
            }
        }

        return new self($period, $entitledHalfDays, $counted[TimeOffStatus::Approved->value], $counted[TimeOffStatus::Pending->value]);
    }

    public function remainingHalfDays(): int
    {
        return $this->entitledHalfDays - $this->approvedHalfDays - $this->pendingHalfDays;
    }

    public function isOverrun(): bool
    {
        return $this->remainingHalfDays() < 0;
    }

    /**
     * The balance were the entitlements these half-days instead.
     */
    public function withEntitlement(int $halfDays): self
    {
        return new self($this->period, $halfDays, $this->approvedHalfDays, $this->pendingHalfDays);
    }

    /**
     * The balance once these half-days are requested as well.
     */
    public function with(int $halfDays): self
    {
        return new self($this->period, $this->entitledHalfDays, $this->approvedHalfDays, $this->pendingHalfDays + $halfDays);
    }
}
