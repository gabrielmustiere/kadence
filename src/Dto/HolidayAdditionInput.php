<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\Type\HolidayCalendar;
use App\Validator\AddableHoliday;
use Symfony\Component\Validator\Constraints as Assert;

#[AddableHoliday]
final class HolidayAdditionInput
{
    #[Assert\NotNull]
    public ?HolidayCalendar $calendar = HolidayCalendar::France;

    #[Assert\NotNull(message: 'Indiquez le jour à rendre férié.')]
    public ?\DateTimeImmutable $day = null;

    #[Assert\NotBlank(message: 'Donnez un libellé au jour férié, par exemple « Remplacement du 1er novembre ».')]
    #[Assert\Length(max: 100)]
    public ?string $label = null;
}
