<?php

declare(strict_types=1);

namespace App\Model\Roadmap;

/**
 * The runs of a project that end in a given month, as listed on the timeline of its page.
 */
final readonly class TimelineMonth
{
    private const array MONTHS = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    /**
     * @param list<TimelineEntry> $entries
     */
    public function __construct(
        public string $label,
        public array $entries,
    ) {
    }

    /**
     * The entries from the latest to the earliest, by last then first day, each under the month of its last day.
     * Entries ending and starting on the same days keep the order they are given in.
     *
     * @param list<TimelineEntry> $entries
     *
     * @return list<self>
     */
    public static function group(array $entries): array
    {
        usort($entries, static fn (TimelineEntry $a, TimelineEntry $b): int => [$b->run->to, $b->run->from] <=> [$a->run->to, $a->run->from]);

        $months = [];
        foreach ($entries as $entry) {
            $months[$entry->run->to->format('Y-m')][] = $entry;
        }

        return array_values(array_map(
            static fn (array $monthEntries): self => new self(self::MONTHS[(int) $monthEntries[0]->run->to->format('n')] . ' ' . $monthEntries[0]->run->to->format('Y'), $monthEntries),
            $months,
        ));
    }
}
