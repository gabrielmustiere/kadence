<?php

declare(strict_types=1);

namespace App\Model\Schedule;

use App\Entity\LotProgress;
use App\Model\Quarters;

/**
 * The progress in force on a leaf: the share of its work declared done, from which what is left is extrapolated on
 * the time entered so far.
 */
final readonly class LeafProgress
{
    public const int REFRESH_DAYS = 7;

    /**
     * @param int<1, 100> $percent
     */
    public function __construct(
        public int $percent,
        public \DateTimeImmutable $declaredOn,
    ) {
    }

    /**
     * None at 0 %, which withdraws the progress.
     */
    public static function fromDeclaration(LotProgress $declaration): ?self
    {
        $percent = $declaration->getPercent();

        return 0 === $percent ? null : new self($percent, $declaration->getDeclaredOn());
    }

    /**
     * What is left to do, rounded up to the quarter: extrapolated from the time entered so far, or taken from the
     * estimate while none is entered.
     *
     * @param int<0, max> $enteredQuarters
     * @param int<0, max> $estimateQuarters
     *
     * @return int<0, max>
     */
    public function remaining(int $enteredQuarters, int $estimateQuarters): int
    {
        $remaining = 0 === $enteredQuarters
            ? intdiv($estimateQuarters * (100 - $this->percent) + 99, 100)
            : intdiv($enteredQuarters * (100 - $this->percent) + $this->percent - 1, $this->percent);

        return max(0, $remaining);
    }

    /**
     * Declaring the progress is a weekly duty: one older than a week is to refresh once time was entered after it, short
     * of 100 %.
     */
    public function isToRefresh(?\DateTimeImmutable $lastEntryDay, \DateTimeImmutable $today): bool
    {
        return !$this->isComplete()
            && $this->declaredOn < $today->setTime(0, 0)->modify(\sprintf('-%d days', self::REFRESH_DAYS))
            && null !== $lastEntryDay && $lastEntryDay > $this->declaredOn;
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
