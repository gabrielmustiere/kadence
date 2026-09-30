<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\Type\HolidayCalendar;

final class HolidayAdjustmentRefusedException extends \DomainException
{
    public static function notALegalHoliday(HolidayCalendar $calendar, \DateTimeImmutable $day): self
    {
        return new self(\sprintf('Le %s n\'est pas un jour férié légal du calendrier %s : il n\'y a rien à retirer.', $day->format('d/m/Y'), $calendar->label()));
    }

    public static function alreadyRemoved(HolidayCalendar $calendar, \DateTimeImmutable $day): self
    {
        return new self(\sprintf('Le %s est déjà retiré du calendrier %s.', $day->format('d/m/Y'), $calendar->label()));
    }
}
