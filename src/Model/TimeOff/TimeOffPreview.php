<?php

declare(strict_types=1);

namespace App\Model\TimeOff;

use App\Enum\Type\TimeOffType;

/**
 * The days a request counts, and for a type with a balance, the balance left on each period it falls in.
 */
final readonly class TimeOffPreview
{
    /**
     * @param list<array{int, Balance}> $periods half-days counted on the period, then the balance once the request is counted
     */
    public function __construct(
        public int $halfDays,
        public array $periods,
    ) {
    }

    /**
     * @param \Closure(TimeOffPeriod): Balance $balanceOf the balance of a period as recorded
     * @param bool                             $isNew     whether the request is not recorded yet, and so not counted in the balances
     */
    public static function of(TimeOffType $type, TimeOffDays $days, \Closure $balanceOf, bool $isNew): self
    {
        $periods = [];
        if ($type->hasBalance()) {
            foreach (array_keys($days->halves) as $day) {
                $period = TimeOffPeriod::containing($type, new \DateTimeImmutable($day));
                $periods[$period->year] = $period;
            }
        }

        return new self($days->halfDayCount(), array_values(array_map(static function (TimeOffPeriod $period) use ($days, $balanceOf, $isNew): array {
            $halfDays = $days->halfDayCountIn($period);
            $balance = $balanceOf($period);

            return [$halfDays, $isNew ? $balance->with($halfDays) : $balance];
        }, $periods)));
    }

    public function isOverrun(): bool
    {
        return array_any($this->periods, static fn (array $period): bool => $period[1]->isOverrun());
    }
}
