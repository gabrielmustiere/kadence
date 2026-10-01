<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Entity\User;

/**
 * A person in the team of a bar of a leaf: a member with their share, or someone outside the team who entered time on
 * it, with the time they entered on that bar.
 */
final readonly class RoadmapTeamLine
{
    /**
     * @param int|null $share null outside the team
     */
    public function __construct(
        public User $user,
        public ?int $share,
        public int $quarters,
    ) {
    }
}
