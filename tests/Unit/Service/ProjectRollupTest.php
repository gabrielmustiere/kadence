<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Service\ProjectRollup;
use PHPUnit\Framework\TestCase;

final class ProjectRollupTest extends TestCase
{
    public function testSplitLotAndProjectAddUpTheirLeaves(): void
    {
        $project = new Project()->setTitle('Kadence');
        $split = $this->lot($project, null, null);
        $this->lot($project, 3, $this->user(), $split);
        $this->lot($project, 5, $this->user(), $split);
        $this->lot($project, 10, $this->user());

        $summary = new ProjectRollup()->summarize($project);

        self::assertSame(18, $summary->estimateDays);
        self::assertCount(2, $summary->lots);
        self::assertSame(8, $summary->lots[0]->estimateDays);
        self::assertCount(2, $summary->lots[0]->children);
        self::assertSame(2, $summary->subLotCount);
        self::assertFalse($summary->isPartial());
        self::assertFalse($summary->isUnsplit());
    }

    public function testLeafWithoutEstimateMakesItsLotAndProjectPartial(): void
    {
        $project = new Project()->setTitle('Kadence');
        $split = $this->lot($project, null, null);
        $this->lot($project, 3, $this->user(), $split);
        $this->lot($project, null, $this->user(), $split);

        $summary = new ProjectRollup()->summarize($project);

        self::assertSame(3, $summary->estimateDays);
        self::assertSame(1, $summary->toEstimate);
        self::assertTrue($summary->isPartial());
        self::assertTrue($summary->lots[0]->isPartial());
        self::assertFalse($summary->lots[0]->children[0]->isPartial());
    }

    public function testCountsLeavesToAssignAndToReassign(): void
    {
        $project = new Project()->setTitle('Kadence');
        $this->lot($project, 2, null);
        $this->lot($project, 2, $this->user(active: false));
        $this->lot($project, 2, $this->user());

        $summary = new ProjectRollup()->summarize($project);

        self::assertSame(1, $summary->toAssign);
        self::assertSame(1, $summary->toReassign);
        self::assertSame(0, $summary->toEstimate);
    }

    public function testEstimateLeftOnASplitLotIsIgnored(): void
    {
        $project = new Project()->setTitle('Kadence');
        $split = $this->lot($project, 40, $this->user());
        $this->lot($project, 3, $this->user(), $split);

        $summary = new ProjectRollup()->summarize($project);

        self::assertSame(3, $summary->estimateDays);
        self::assertSame(0, $summary->toAssign);
    }

    public function testProjectWithoutLotIsUnsplit(): void
    {
        $summary = new ProjectRollup()->summarize(new Project()->setTitle('Portail'));

        self::assertTrue($summary->isUnsplit());
        self::assertSame(0, $summary->estimateDays);
        self::assertFalse($summary->isPartial());
    }

    /** @param positive-int|null $estimateDays */
    private function lot(Project $project, ?int $estimateDays, ?User $owner, ?Lot $parent = null): Lot
    {
        return new Lot($project, $parent)->setTitle('Lot')->setEstimateDays($estimateDays)->setOwner($owner);
    }

    private function user(bool $active = true): User
    {
        return new User()->setFirstName('Paula')->setLastName('Durand')->setEmail('paula@example.com')->setActive($active);
    }
}
