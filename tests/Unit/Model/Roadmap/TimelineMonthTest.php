<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Roadmap;

use App\Entity\Lot;
use App\Entity\Project;
use App\Model\Roadmap\RoadmapRun;
use App\Model\Roadmap\TimelineEntry;
use App\Model\Roadmap\TimelineMonth;
use PHPUnit\Framework\TestCase;

final class TimelineMonthTest extends TestCase
{
    public function testEntriesGoFromTheLatestToTheEarliestUnderTheMonthOfTheirLastDay(): void
    {
        $api = $this->leaf('API');
        $login = $this->leaf('Login', 'Front');

        $months = TimelineMonth::group([
            $this->entry($api, '2026-08-27', '2026-08-28'),
            $this->entry($login, '2026-08-31', '2026-09-01'),
            $this->entry($api, '2026-09-21', '2026-09-25'),
            $this->entry($api, '2026-09-24', '2026-09-25'),
        ]);

        self::assertSame(['Septembre 2026', 'Août 2026'], array_map(static fn (TimelineMonth $month): string => $month->label, $months));
        self::assertSame([['2026-09-24', '2026-09-25'], ['2026-09-21', '2026-09-25'], ['2026-08-31', '2026-09-01']], self::days($months[0]->entries), 'On the same last day, the latest first day comes first.');
        self::assertSame([['2026-08-27', '2026-08-28']], self::days($months[1]->entries));
    }

    public function testEntriesOnTheSameDaysKeepTheOrderOfTheirLeaves(): void
    {
        $first = $this->entry($this->leaf('Premier'), '2026-09-07', '2026-09-11');
        $second = $this->entry($this->leaf('Second'), '2026-09-07', '2026-09-11');

        self::assertSame([$first, $second], TimelineMonth::group([$first, $second])[0]->entries);
    }

    public function testNoEntryMakesNoMonth(): void
    {
        self::assertSame([], TimelineMonth::group([]));
    }

    public function testLeafPathNamesTheLotOfASubLot(): void
    {
        self::assertSame('Front · Login', $this->entry($this->leaf('Login', 'Front'), '2026-09-14', '2026-09-16')->leafPath());
        self::assertSame('API', $this->entry($this->leaf('API'), '2026-09-14', '2026-09-16')->leafPath());
    }

    /** @param non-empty-string $title */
    private function leaf(string $title, ?string $parentTitle = null): Lot
    {
        $project = new Project()->setTitle('Refonte');
        $parent = null === $parentTitle || '' === $parentTitle ? null : new Lot($project)->setTitle($parentTitle);

        return new Lot($project, $parent)->setTitle($title);
    }

    private function entry(Lot $leaf, string $from, string $to): TimelineEntry
    {
        return new TimelineEntry($leaf, new RoadmapRun(new \DateTimeImmutable($from), new \DateTimeImmutable($to), 4, 1), false);
    }

    /**
     * @param list<TimelineEntry> $entries
     *
     * @return list<array{string, string}>
     */
    private static function days(array $entries): array
    {
        return array_map(static fn (TimelineEntry $entry): array => [$entry->run->from->format('Y-m-d'), $entry->run->to->format('Y-m-d')], $entries);
    }
}
