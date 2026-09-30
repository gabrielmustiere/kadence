<?php

declare(strict_types=1);

namespace App\Model\Schedule;

final readonly class LeafSchedule
{
    /**
     * @param \DateTimeImmutable|null $realizedFrom      first day with time entered on the leaf
     * @param \DateTimeImmutable|null $realizedTo        last day with time entered on the leaf
     * @param \DateTimeImmutable|null $futureFrom        first day the team gives capacity to the leaf
     * @param \DateTimeImmutable|null $futureTo          day the remaining time is covered, null when it is not within the horizon
     * @param int|null                $remainingQuarters estimate minus consumed time, null while « à estimer »
     */
    public function __construct(
        public int $lotId,
        public ?\DateTimeImmutable $realizedFrom,
        public ?\DateTimeImmutable $realizedTo,
        public ?\DateTimeImmutable $futureFrom,
        public ?\DateTimeImmutable $futureTo,
        public ?int $remainingQuarters,
        public bool $toEstimate,
        public bool $withoutStart,
        public bool $withoutTeam,
        public bool $lateStart = false,
        public bool $estimateExhausted = false,
        public bool $teamToReview = false,
    ) {
    }

    public function isPlanned(): bool
    {
        return !$this->toEstimate && !$this->withoutStart && !$this->withoutTeam;
    }

    public function hasFuture(): bool
    {
        return null !== $this->futureFrom && null !== $this->futureTo;
    }

    public function start(): ?\DateTimeImmutable
    {
        $future = $this->hasFuture() ? $this->futureFrom : null;
        if (null === $this->realizedFrom || null === $future) {
            return $this->realizedFrom ?? $future;
        }

        return min($this->realizedFrom, $future);
    }

    /**
     * Exactly the estimate entered: the leaf ends on its last day entered.
     */
    public function isEstimateReached(): bool
    {
        return $this->estimateExhausted && 0 === $this->remainingQuarters;
    }

    /**
     * More than the estimate entered: the work may go on, but nothing tells for how long until the estimate is revised.
     */
    public function isOverrun(): bool
    {
        return null !== $this->remainingQuarters && $this->remainingQuarters < 0;
    }

    /**
     * The calculated end: the last day entered once the estimate is reached; none when it is overrun or when the team
     * cannot cover the remaining time.
     */
    public function end(): ?\DateTimeImmutable
    {
        if ($this->estimateExhausted) {
            return $this->isOverrun() ? null : $this->realizedTo;
        }

        return $this->hasFuture() ? $this->futureTo : null;
    }
}
