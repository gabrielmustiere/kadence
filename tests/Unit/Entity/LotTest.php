<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Lot;
use App\Entity\Project;
use App\Exception\LotDepthException;
use PHPUnit\Framework\TestCase;

final class LotTest extends TestCase
{
    public function testASubLotJoinsItsLotAndProject(): void
    {
        $project = new Project();
        $lot = new Lot($project);

        $subLot = new Lot($project, $lot);

        self::assertSame($lot, $subLot->getParent());
        self::assertTrue($lot->getChildren()->contains($subLot));
        self::assertTrue($project->getLots()->contains($subLot));
        self::assertFalse($lot->isLeaf());
    }

    public function testASubLotCannotBeSplitIntoSubLots(): void
    {
        $project = new Project();
        $subLot = new Lot($project, new Lot($project));

        $this->expectException(LotDepthException::class);

        new Lot($project, $subLot);
    }

    public function testASubLotCannotBelongToAnotherProjectThanItsLot(): void
    {
        $lot = new Lot(new Project());

        $this->expectExceptionObject(new \LogicException('A sub-lot belongs to the project of its lot.'));

        new Lot(new Project(), $lot);
    }

    public function testFreezingKeepsTheEstimateInForceAsTheInitialEstimate(): void
    {
        $leaf = new Lot(new Project())->setEstimateDays(5);

        $leaf->freezeInitialEstimate();

        self::assertSame(5, $leaf->getInitialEstimateDays());
    }

    public function testAFrozenInitialEstimateNoLongerFollowsTheEstimate(): void
    {
        $leaf = new Lot(new Project())->setEstimateDays(5)->freezeInitialEstimate();

        $leaf->setEstimateDays(8)->freezeInitialEstimate();

        self::assertSame(5, $leaf->getInitialEstimateDays());
        self::assertSame(8, $leaf->getEstimateDays());
    }

    public function testALeafToEstimateIsFrozenOnceEstimated(): void
    {
        $leaf = new Lot(new Project())->freezeInitialEstimate();
        self::assertNull($leaf->getInitialEstimateDays());

        $leaf->setEstimateDays(3)->freezeInitialEstimate();

        self::assertSame(3, $leaf->getInitialEstimateDays());
    }
}
