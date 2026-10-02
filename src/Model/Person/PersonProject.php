<?php

declare(strict_types=1);

namespace App\Model\Person;

use App\Entity\Project;

/**
 * A project on the page of a person: the leaves the person entered time on or is in the team of.
 */
final readonly class PersonProject
{
    /**
     * @param non-empty-list<PersonLeafRow> $rows
     * @param \DateTimeImmutable|null       $lastEnteredDay null when the person entered nothing on the project
     */
    public function __construct(
        public Project $project,
        public array $rows,
        public int $enteredQuarters,
        public ?\DateTimeImmutable $lastEnteredDay,
    ) {
    }
}
