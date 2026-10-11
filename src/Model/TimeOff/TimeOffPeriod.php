<?php

declare(strict_types=1);

namespace App\Model\TimeOff;

use App\Enum\Type\TimeOffType;

/**
 * The period a type with a balance counts its days over: from May 1st to April 30th for the paid leave, the calendar
 * year for the RTT. It is named after the year it starts in.
 */
final readonly class TimeOffPeriod
{
    private function __construct(
        public TimeOffType $type,
        public int $year,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the type has no balance
     */
    public static function of(TimeOffType $type, int $year): self
    {
        if (!$type->hasBalance()) {
            throw new \InvalidArgumentException(\sprintf('« %s » is not counted over a period.', $type->label()));
        }

        return new self($type, $year);
    }

    public static function containing(TimeOffType $type, \DateTimeImmutable $day): self
    {
        $year = (int) $day->format('Y');
        if (TimeOffType::PaidLeave === $type && (int) $day->format('n') < 5) {
            --$year;
        }

        return self::of($type, $year);
    }

    public function firstDay(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf(TimeOffType::PaidLeave === $this->type ? '%d-05-01' : '%d-01-01', $this->year));
    }

    public function lastDay(): \DateTimeImmutable
    {
        return TimeOffType::PaidLeave === $this->type
            ? new \DateTimeImmutable(\sprintf('%d-04-30', $this->year + 1))
            : new \DateTimeImmutable(\sprintf('%d-12-31', $this->year));
    }

    public function contains(\DateTimeImmutable $day): bool
    {
        $date = $day->format('Y-m-d');

        return $date >= $this->firstDay()->format('Y-m-d') && $date <= $this->lastDay()->format('Y-m-d');
    }

    public function label(): string
    {
        return TimeOffType::PaidLeave === $this->type ? \sprintf('%d-%d', $this->year, $this->year + 1) : (string) $this->year;
    }

    public function previous(): self
    {
        return new self($this->type, $this->year - 1);
    }

    public function next(): self
    {
        return new self($this->type, $this->year + 1);
    }

    /**
     * Identifies the period among those of every type.
     */
    public function key(): string
    {
        return $this->type->value . '@' . $this->year;
    }
}
