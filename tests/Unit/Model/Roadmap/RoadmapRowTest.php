<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Roadmap;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapTeamLine;
use App\Model\Schedule\LeafProgress;
use PHPUnit\Framework\TestCase;

final class RoadmapRowTest extends TestCase
{
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
}
