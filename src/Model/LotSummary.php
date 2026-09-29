<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Lot;

final readonly class LotSummary
{
    /**
     * @param list<LotSummary> $children
     * @param int              $toEstimate leaves without an estimate, the lot itself included when it is a leaf
     * @param int              $toAssign   leaves without an owner
     * @param int              $toReassign leaves whose owner has been deactivated
     */
    public function __construct(
        public Lot $lot,
        public array $children,
        public int $estimateDays,
        public int $toEstimate,
        public int $toAssign,
        public int $toReassign,
    ) {
    }

    public function isPartial(): bool
    {
        return $this->toEstimate > 0;
    }
}
