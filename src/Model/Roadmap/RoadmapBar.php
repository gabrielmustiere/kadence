<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

/**
 * A span of days placed on the window, in percent of its width; cut at an edge when it goes beyond it.
 */
final readonly class RoadmapBar
{
    public function __construct(
        public float $left,
        public float $width,
        public bool $cutStart,
        public bool $cutEnd,
    ) {
    }

    public function style(): string
    {
        return \sprintf('left: %.4F%%; width: %.4F%%', $this->left, $this->width);
    }
}
