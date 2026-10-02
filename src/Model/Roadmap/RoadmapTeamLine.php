<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Entity\Lot;
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

    /**
     * @param array<int, int>  $quartersByUser
     * @param array<int, User> $people         by user id
     *
     * @return list<self> the members with their share, then the people outside the team by time entered
     */
    public static function forLeaf(Lot $leaf, array $quartersByUser, array $people): array
    {
        $lines = [];
        foreach ($leaf->getMembers() as $member) {
            $userId = (int) $member->getUser()->getId();
            $lines[] = new self($member->getUser(), $member->getShare(), $quartersByUser[$userId] ?? 0);
            unset($quartersByUser[$userId]);
        }

        arsort($quartersByUser);
        foreach (array_filter($quartersByUser) as $userId => $quarters) {
            if (isset($people[$userId])) {
                $lines[] = new self($people[$userId], null, $quarters);
            }
        }

        return $lines;
    }
}
