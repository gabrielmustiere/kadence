<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

/**
 * A span of days placed on the window, in percent of its width; cut at an edge when it goes beyond it, while $from and
 * $to keep its whole span.
 */
final readonly class RoadmapBar
{
    /**
     * @param list<RoadmapBar> $segments the runs of days entered that make up the bar, placed in percent of its width;
     *                                   none when the bar is whole
     */
    public function __construct(
        public float $left,
        public float $width,
        public bool $cutStart,
        public bool $cutEnd,
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public array $segments = [],
    ) {
    }

    public function style(): string
    {
        return \sprintf('left: %.4F%%; width: %.4F%%', $this->left, $this->width);
    }
}
