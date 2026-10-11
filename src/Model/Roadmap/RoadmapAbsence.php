<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Entity\User;

/**
 * Days in a row of approved time off of a member of the team of a leaf, named on the roadmap without its type.
 */
final readonly class RoadmapAbsence
{
    /**
     * @param bool $half a single half-day off
     */
    public function __construct(
        public User $person,
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public bool $half,
        public RoadmapBar $bar,
    ) {
    }
}
