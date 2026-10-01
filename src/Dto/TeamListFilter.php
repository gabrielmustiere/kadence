<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Tag;
use App\Entity\User;

final class TeamListFilter
{
    public ?Tag $technicalSkill = null;

    public ?Tag $functionalExperience = null;

    public ?Tag $teamType = null;

    public ?User $manager = null;

    /**
     * @return list<Tag> the tags a person must all carry
     */
    public function tags(): array
    {
        return array_values(array_filter([$this->technicalSkill, $this->functionalExperience, $this->teamType]));
    }
}
