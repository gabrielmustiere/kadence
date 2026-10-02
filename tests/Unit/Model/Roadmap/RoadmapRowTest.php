<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Roadmap;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapTeamLine;
use App\Model\Schedule\LeafProgress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoadmapRowTest extends TestCase
{
    /**
     * @return iterable<string, array{positive-int, int<0, max>, int|null}>
     */
    public static function overruns(): iterable
    {
        yield '150 j entered for 100 j estimated' => [100, 200, 50];
        yield 'rounded to the nearest percent' => [81, 85, 26];
        yield 'never down to 0 % once overrun' => [81, 1, 1];
        yield 'none within the estimate' => [10, 0, null];
    }

    /**
     * @param positive-int $estimateDays
     * @param int<0, max>  $overrunQuarters
     */
    #[DataProvider('overruns')]
    public function testOverrunPercentIsTheTimeBeyondTheEstimateOverTheEstimate(int $estimateDays, int $overrunQuarters, ?int $percent): void
    {
        $project = new Project()->setTitle('Nova');
        $row = new RoadmapRow($project, new Lot($project)->setTitle('Sync')->setEstimateDays($estimateDays), overrunQuarters: $overrunQuarters);

        self::assertSame($percent, $row->overrunPercent());
    }

    public function testProjectedCostIsTheTimeEnteredPlusWhatTheProgressLeaves(): void
    {
        $project = new Project()->setTitle('Nova');
        $lot = new Lot($project)->setTitle('Sync')->setEstimateDays(20);
        $team = [new RoadmapTeamLine(new User(), 100, 40)];
        $progress = new LeafProgress(40, new \DateTimeImmutable('2026-10-02'), 40, 60);

        $row = new RoadmapRow($project, $lot, remainingQuarters: 60, team: $team, progress: $progress);
        self::assertSame(100, $row->projectedQuarters());
        self::assertSame(20, $row->projectedGapQuarters());
        $withoutProgress = new RoadmapRow($project, $lot, remainingQuarters: 40, team: $team);
        self::assertNull($withoutProgress->projectedQuarters());
        self::assertNull($withoutProgress->projectedGapQuarters());
    }

    public function testNoOverrunPercentWhileToEstimate(): void
    {
        $project = new Project()->setTitle('Nova');
        $row = new RoadmapRow($project, new Lot($project)->setTitle('Sync'));

        self::assertNull($row->overrunPercent());
    }
}
