<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

use App\Enum\Type\RoadmapSignal;

/**
 * The board above the roadmap of a lead or the direction: by family, how many leaves await the action of each signal,
 * across every project whatever the window shown.
 */
final readonly class RoadmapBoard
{
    private const array FAMILIES = [
        'Dérives' => [RoadmapSignal::Overrun, RoadmapSignal::ToReplan, RoadmapSignal::TeamToReview],
        'Planification' => [RoadmapSignal::ToEstimate, RoadmapSignal::WithoutStart, RoadmapSignal::WithoutTeam, RoadmapSignal::LateStart],
        'Avancement' => [RoadmapSignal::ProgressToRefresh],
    ];

    /**
     * @param array<string, non-empty-list<RoadmapCounter>> $families the families with a counter above 0, in order
     */
    private function __construct(
        public array $families,
    ) {
    }

    public static function of(Roadmap $roadmap): self
    {
        $families = [];
        foreach (self::FAMILIES as $family => $signals) {
            $counters = array_values(array_filter(array_map(static fn (RoadmapSignal $signal): ?RoadmapCounter => self::counter($roadmap, $signal), $signals)));
            if ([] !== $counters) {
                $families[$family] = $counters;
            }
        }

        return new self($families);
    }

    private static function counter(Roadmap $roadmap, RoadmapSignal $signal): ?RoadmapCounter
    {
        $leafCount = 0;
        $projectCount = 0;
        foreach ($roadmap->projects as $project) {
            $awaiting = \count($project->leavesAwaiting($signal));
            $leafCount += $awaiting;
            $projectCount += $awaiting > 0 ? 1 : 0;
        }

        return $leafCount > 0 && $projectCount > 0 ? new RoadmapCounter($signal, $leafCount, $projectCount) : null;
    }

    /**
     * The signals a counter of the board may stand for.
     *
     * @return list<RoadmapSignal>
     */
    public static function signals(): array
    {
        return array_merge(...array_values(self::FAMILIES));
    }

    public function isEmpty(): bool
    {
        return [] === $this->families;
    }
}
