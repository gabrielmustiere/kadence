<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Type\HolidayAdjustmentType;
use App\Enum\Type\HolidayCalendar;
use App\Repository\HolidayAdjustmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A departure from the law on one day of one calendar: a holiday added (e.g. a Belgian replacement day) or a legal
 * holiday removed (e.g. a worked Whit Monday).
 */
#[ORM\Entity(repositoryClass: HolidayAdjustmentRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_holiday_adjustment_calendar_day', columns: ['calendar', 'day'])]
class HolidayAdjustment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    #[ORM\Column(name: 'calendar', length: 2, enumType: HolidayCalendar::class)]
    private HolidayCalendar $calendar;

    #[ORM\Column(name: 'day', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $day;

    #[ORM\Column(name: 'type', length: 10, enumType: HolidayAdjustmentType::class)]
    private HolidayAdjustmentType $type;

    /** @var non-empty-string|null set for an added holiday only: a removed one keeps its legal label */
    #[ORM\Column(name: 'label', length: 100, nullable: true)]
    private ?string $label;

    /** @param non-empty-string|null $label */
    private function __construct(HolidayCalendar $calendar, \DateTimeImmutable $day, HolidayAdjustmentType $type, ?string $label)
    {
        $this->calendar = $calendar;
        $this->day = $day->setTime(0, 0);
        $this->type = $type;
        $this->label = $label;
    }

    /** @param non-empty-string $label */
    public static function added(HolidayCalendar $calendar, \DateTimeImmutable $day, string $label): self
    {
        return new self($calendar, $day, HolidayAdjustmentType::Added, $label);
    }

    public static function removed(HolidayCalendar $calendar, \DateTimeImmutable $day): self
    {
        return new self($calendar, $day, HolidayAdjustmentType::Removed, null);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCalendar(): HolidayCalendar
    {
        return $this->calendar;
    }

    public function getDay(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function getType(): HolidayAdjustmentType
    {
        return $this->type;
    }

    public function isAdded(): bool
    {
        return HolidayAdjustmentType::Added === $this->type;
    }

    /** @return non-empty-string|null */
    public function getLabel(): ?string
    {
        return $this->label;
    }
}
