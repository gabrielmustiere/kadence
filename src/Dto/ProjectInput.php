<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Project;
use App\Validator\UniqueProjectTitle;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueProjectTitle]
final class ProjectInput
{
    public ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public ?string $title = null;

    public ?string $description = null;

    public static function fromProject(Project $project): self
    {
        $input = new self();
        $input->id = $project->getId();
        $input->title = $project->getTitle();
        $input->description = $project->getDescription();

        return $input;
    }
}
