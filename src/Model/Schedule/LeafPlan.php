<?php

declare(strict_types=1);

namespace App\Model\Schedule;

final readonly class LeafPlan
{
    /**
     * @param int|null            $estimateQuarters null while the leaf is « à estimer »
     * @param list<PlannedMember> $members
     * @param LeafProgress|null   $progress         the progress in force, none at 0 % or when never declared
     */
    public function __construct(
        public int $lotId,
        public ?int $estimateQuarters,
        public int $consumedQuarters,
        public ?\DateTimeImmutable $firstEntryDay,
        public ?\DateTimeImmutable $lastEntryDay,
        public ?\DateTimeImmutable $startDate,
        public array $members,
        public int $enteredDayCount = 0,
        public ?LeafProgress $progress = null,
    ) {
    }

    /**
     * @param list<PlannedMember> $members
     */
    public function withPlanning(?int $estimateQuarters, ?\DateTimeImmutable $startDate, array $members): self
    {
        return new self($this->lotId, $estimateQuarters, $this->consumedQuarters, $this->firstEntryDay, $this->lastEntryDay, $startDate, $members, $this->enteredDayCount, $this->progress);
    }
}
