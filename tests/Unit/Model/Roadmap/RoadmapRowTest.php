<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Roadmap;

use App\Entity\Lot;
use App\Entity\Project;
use App\Model\Roadmap\RoadmapRow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoadmapRowTest extends TestCase
{
    /**
     * @return iterable<string, array{positive-int, int, int|null}>
     */
    public static function overruns(): iterable
    {
        yield '150 j entered for 100 j estimated' => [100, -200, 50];
        yield 'rounded to the nearest percent' => [81, -85, 26];
        yield 'never down to 0 % once overrun' => [81, -1, 1];
        yield 'none within the estimate' => [10, 8, null];
        yield 'none when the estimate is just reached' => [10, 0, null];
    }

    /**
     * @param positive-int $estimateDays
     */
    #[DataProvider('overruns')]
    public function testOverrunPercentIsTheTimeBeyondTheEstimateOverTheEstimate(int $estimateDays, int $remainingQuarters, ?int $percent): void
    {
        $project = new Project()->setTitle('Nova');
        $row = new RoadmapRow($project, new Lot($project)->setTitle('Sync')->setEstimateDays($estimateDays), remainingQuarters: $remainingQuarters);

        self::assertSame($percent, $row->overrunPercent());
    }

    public function testNoOverrunPercentWhileToEstimate(): void
    {
        $project = new Project()->setTitle('Nova');
        $row = new RoadmapRow($project, new Lot($project)->setTitle('Sync'));

        self::assertNull($row->overrunPercent());
    }
}
