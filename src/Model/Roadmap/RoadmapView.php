<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Enum\Type\RoadmapSignal;
use App\Model\Week;

/**
 * What the roadmap shows, carried by its links and those leading back to it: the week it is anchored on, and the
 * signal of the board it is restricted to, kept for leads and the direction only.
 */
final readonly class RoadmapView
{
    private function __construct(
        public Week $week,
        public ?RoadmapSignal $signal,
    ) {
    }

    /**
     * An unknown signal, one without a counter on the board, or one asked for by someone who does not plan is left out.
     *
     * @param bool $planner whether the reader is a lead or the direction
     */
    public static function of(Week $week, ?string $signal, bool $planner): self
    {
        $retained = $planner && null !== $signal ? RoadmapSignal::tryFrom($signal) : null;

        return new self($week, \in_array($retained, RoadmapBoard::signals(), true) ? $retained : null);
    }

    /**
     * Null when the week is missing or does not exist.
     *
     * @param bool $planner whether the reader is a lead or the direction
     */
    public static function tryFrom(?string $week, ?string $signal, bool $planner): ?self
    {
        $anchor = null === $week ? null : Week::tryFromIso($week);

        return null === $anchor ? null : self::of($anchor, $signal, $planner);
    }

    /**
     * @return array{signal?: string}
     */
    public function signalParams(): array
    {
        return null === $this->signal ? [] : ['signal' => $this->signal->value];
    }

    /**
     * @return array{week: string, signal?: string}
     */
    public function routeParams(): array
    {
        return ['week' => $this->week->iso(), ...$this->signalParams()];
    }
}
