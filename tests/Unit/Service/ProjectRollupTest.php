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

    public function testTimeOnALeafMarksItsSplitLotAndItsProject(): void
    {
        $project = new Project()->setTitle('Kadence');
        $split = $this->lot($project, null, null);
        $this->lot($project, 3, $this->user(), $split);

        $withoutTime = new ProjectRollup()->summarize($project);
        $withTime = new ProjectRollup()->summarize($project, [0 => 2]);

        self::assertFalse($withoutTime->hasTime);
        self::assertFalse($withoutTime->lots[0]->hasTime);
        self::assertTrue($withTime->hasTime);
        self::assertTrue($withTime->lots[0]->hasTime);
        self::assertTrue($withTime->lots[0]->children[0]->hasTime);
    }

    public function testLeafTellsWhatIsEnteredThenWhatRemainsOrGoesBeyondItsEstimate(): void
    {
        $project = new Project()->setTitle('Kadence');
        $this->lot($project, 10, $this->user(), id: 1);
        $this->lot($project, 8, $this->user(), id: 2);

        $summary = new ProjectRollup()->summarize($project, [1 => 40, 2 => 12]);

        [$done, $started] = $summary->lots;
        self::assertSame([40, 0, 0], [$done->enteredQuarters, $done->remainingQuarters, $done->overrunQuarters]);
        self::assertSame([12, 20, 0], [$started->enteredQuarters, $started->remainingQuarters, $started->overrunQuarters]);
        self::assertSame([52, 20, 0], [$summary->enteredQuarters, $summary->remainingQuarters, $summary->overrunQuarters]);
    }

    public function testSplitLotAndProjectAddUpWhatRemainsAndWhatGoesBeyondSeparately(): void
    {
        $project = new Project()->setTitle('Kadence');
        $split = $this->lot($project, null, null, id: 1);
        $this->lot($project, 5, $this->user(), $split, 2);
        $this->lot($project, 5, $this->user(), $split, 3);
        $this->lot($project, 4, $this->user(), id: 4);

        $summary = new ProjectRollup()->summarize($project, [2 => 40, 3 => 8, 4 => 4]);

        $lot = $summary->lots[0];
        self::assertSame([0, 20], [$lot->children[0]->remainingQuarters, $lot->children[0]->overrunQuarters]);
        self::assertSame([12, 0], [$lot->children[1]->remainingQuarters, $lot->children[1]->overrunQuarters]);
        self::assertSame([48, 12, 20], [$lot->enteredQuarters, $lot->remainingQuarters, $lot->overrunQuarters], '3 j left on one sub-lot do not offset 5 j beyond the other.');
        self::assertSame([52, 24, 20], [$summary->enteredQuarters, $summary->remainingQuarters, $summary->overrunQuarters]);
    }

    public function testLeafToEstimateHasTimeEnteredButNothingLeftNorBeyond(): void
    {
        $project = new Project()->setTitle('Kadence');
        $this->lot($project, null, $this->user(), id: 1);

        $summary = new ProjectRollup()->summarize($project, [1 => 6]);

        self::assertSame([6, 0, 0], [$summary->lots[0]->enteredQuarters, $summary->lots[0]->remainingQuarters, $summary->lots[0]->overrunQuarters]);
        self::assertTrue($summary->isPartial());
    }

    /** @param positive-int|null $estimateDays */
    private function lot(Project $project, ?int $estimateDays, ?User $owner, ?Lot $parent = null, ?int $id = null): Lot
    {
        $lot = new Lot($project, $parent)->setTitle('Lot')->setEstimateDays($estimateDays)->setOwner($owner);
        if (null !== $id) {
            new \ReflectionProperty(Lot::class, 'id')->setValue($lot, $id);
        }

        return $lot;
    }

    private function user(bool $active = true): User
    {
        return new User()->setFirstName('Paula')->setLastName('Durand')->setEmail('paula@example.com')->setActive($active);
    }
}
