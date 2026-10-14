<?php

declare(strict_types=1);

namespace App\Model\Schedule;

final readonly class PlannedMember
{
    /**
     * @param int<10, 100> $share
     */
    public function __construct(
        public int $userId,
        public int $share,
    ) {
    }
}
