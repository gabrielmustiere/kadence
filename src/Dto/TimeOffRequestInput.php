<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use App\Enum\Type\HalfDay;
use App\Enum\Type\TimeOffType;
use App\Validator\RequestableTimeOff;
use Symfony\Component\Validator\Constraints as Assert;

#[RequestableTimeOff]
final class TimeOffRequestInput
{
    #[Assert\NotNull(message: 'Choisissez le type de congé.')]
    public ?TimeOffType $type = TimeOffType::PaidLeave;

    #[Assert\NotNull(message: 'Indiquez le premier jour du congé.')]
    public ?\DateTimeImmutable $startDay = null;

    #[Assert\NotNull]
    public ?HalfDay $startHalf = HalfDay::Morning;

    #[Assert\NotNull(message: 'Indiquez le dernier jour du congé.')]
    public ?\DateTimeImmutable $endDay = null;

    #[Assert\NotNull]
    public ?HalfDay $endHalf = HalfDay::Afternoon;

    #[Assert\Length(max: 500)]
    public ?string $comment = null;

    public function __construct(
        public readonly User $requester,
    ) {
    }
}
