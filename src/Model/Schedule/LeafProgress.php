<?php

declare(strict_types=1);

namespace App\Model\Schedule;

use App\Entity\LotProgress;
use App\Model\Quarters;

/**
 * The progress in force on a leaf: what was left to do when it was declared, which the time entered since uses up.
 */
final readonly class LeafProgress
{
    /**
     * @param int<1, 100> $percent
     * @param int<0, max> $enteredQuarters   time entered on the leaf when the progress was declared
     * @param int<0, max> $remainingQuarters what was left to do when the progress was declared
     */
    public function __construct(
        public int $percent,
        public \DateTimeImmutable $declaredOn,
        public int $enteredQuarters,
        public int $remainingQuarters,
    ) {
    }

    /**
     * None at 0 %, which withdraws the progress.
     */
    public static function fromDeclaration(LotProgress $declaration): ?self
    {
        $percent = $declaration->getPercent();
        $remaining = $declaration->getRemainingQuarters();
        if (0 === $percent || null === $remaining) {
            return null;
        }

        return new self($percent, $declaration->getDeclaredOn(), $declaration->getEnteredQuarters(), $remaining);
    }

    /**
     * What is left to do at the declaration, rounded up to the quarter: extrapolated from the pace observed, or taken
     * from the estimate when no time is entered yet.
     *
     * @param int<1, 100> $percent
     * @param int<0, max> $enteredQuarters
     * @param int<0, max> $estimateQuarters
     *
     * @return int<0, max>
     */
    public static function anchoredRemaining(int $percent, int $enteredQuarters, int $estimateQuarters): int
    {
        $remaining = 0 === $enteredQuarters
            ? intdiv($estimateQuarters * (100 - $percent) + 99, 100)
            : intdiv($enteredQuarters * (100 - $percent) + $percent - 1, $percent);

        return max(0, $remaining);
    }

    /**
     * What is left to do once the time entered since the declaration is used up, below 0 when it is overtaken; time
     * removed since gives some back, except to a leaf declared complete.
     */
    public function remainingAfter(int $consumedQuarters): int
    {
        $remaining = $this->remainingQuarters - ($consumedQuarters - $this->enteredQuarters);

        return $this->isComplete() ? min(0, $remaining) : $remaining;
    }

    public function isComplete(): bool
    {
        return 100 === $this->percent;
    }

    /**
     * The weight of an estimated leaf in the progress of its lot and project, in percent × quarters: its progress in
     * force, or else the share of its estimate already entered, times its estimate.
     */
    public static function pointsOf(?self $progress, int $enteredQuarters, int $estimateQuarters): int
    {
        return null === $progress ? 100 * min($enteredQuarters, $estimateQuarters) : $progress->percent * $estimateQuarters;
    }

    /**
     * The progress of estimated leaves weighted by their estimate; null while none is estimated.
     */
    public static function weightedPercent(int $points, int $estimateDays): ?int
    {
        return 0 === $estimateDays ? null : (int) round($points / ($estimateDays * Quarters::PER_DAY));
    }
}
