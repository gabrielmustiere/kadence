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
     * @param int|null                $remainingQuarters what is left to do, from the progress in force or else the estimate
     *                                                   minus the consumed time, never below 0; null while « à estimer »
     * @param bool                    $exhausted         nothing is left to do on a planned leaf
     * @param int                     $overrunQuarters   time entered beyond the estimate, 0 within it
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
        public bool $exhausted = false,
        public bool $teamToReview = false,
        public int $overrunQuarters = 0,
        public ?LeafProgress $progress = null,
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
     * Exactly the estimate entered, without any progress declared: the leaf ends on its last day entered.
     */
    public function isEstimateReached(): bool
    {
        return $this->exhausted && null === $this->progress && 0 === $this->overrunQuarters;
    }

    public function isOverrun(): bool
    {
        return $this->overrunQuarters > 0;
    }

    /**
     * Declared at 100 %: the leaf ends on its last day entered.
     */
    public function isCompleted(): bool
    {
        return $this->exhausted && true === $this->progress?->isComplete();
    }

    /**
     * The time entered since the progress was declared has used up what was left: nothing tells how long the work
     * goes on until a new progress is declared.
     */
    public function isProgressToRefresh(): bool
    {
        return $this->exhausted && null !== $this->progress && !$this->progress->isComplete();
    }

    /**
     * The calculated end: the last day entered once the estimate is reached or the leaf is declared complete; none
     * when nothing is left to do otherwise, or when the team cannot cover the remaining time.
     */
    public function end(): ?\DateTimeImmutable
    {
        if ($this->exhausted) {
            return $this->isEstimateReached() || $this->isCompleted() ? $this->realizedTo : null;
        }

        return $this->hasFuture() ? $this->futureTo : null;
    }
}
