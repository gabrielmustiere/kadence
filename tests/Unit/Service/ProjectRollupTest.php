<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\LotProgressRepository;
use App\Repository\TimeEntryRepository;
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

        $summary = $this->rollup()->outline($project);

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

        $summary = $this->rollup()->outline($project);

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

        $summary = $this->rollup()->outline($project);

        self::assertSame(1, $summary->toAssign);
        self::assertSame(1, $summary->toReassign);
        self::assertSame(0, $summary->toEstimate);
    }

    public function testEstimateLeftOnASplitLotIsIgnored(): void
    {
        $project = new Project()->setTitle('Kadence');
        $split = $this->lot($project, 40, $this->user());
        $this->lot($project, 3, $this->user(), $split);

        $summary = $this->rollup()->outline($project);

        self::assertSame(3, $summary->estimateDays);
        self::assertSame(0, $summary->toAssign);
    }

    public function testAnOutlineReadsNeitherTheTimeEnteredNorTheProgressDeclared(): void
    {
        $project = new Project()->setTitle('Kadence');
        $this->lot($project, 10, $this->user(), id: 1);
        $timeEntryRepository = $this->createMock(TimeEntryRepository::class);
        $timeEntryRepository->expects($this->never())->method('sumQuartersByLot');
        $lotProgressRepository = $this->createMock(LotProgressRepository::class);
        $lotProgressRepository->expects($this->never())->method('findForProjectByLot');

        $outline = new ProjectRollup($timeEntryRepository, $lotProgressRepository)->outline($project);

        self::assertSame(10, $outline->estimateDays);
        self::assertSame([0, 0], [$outline->enteredQuarters, $outline->progressPoints]);
    }

    public function testProjectWithoutLotIsUnsplit(): void
    {
        $summary = $this->rollup()->outline(new Project()->setTitle('Portail'));

        self::assertTrue($summary->isUnsplit());
        self::assertSame(0, $summary->estimateDays);
        self::assertFalse($summary->isPartial());
    }

    public function testTimeOnALeafMarksItsSplitLotAndItsProject(): void
    {
        $project = new Project()->setTitle('Kadence');
        $split = $this->lot($project, null, null);
        $this->lot($project, 3, $this->user(), $split);

        $withoutTime = $this->rollup()->summarize($project);
        $withTime = $this->rollup([0 => 2])->summarize($project);

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

        $summary = $this->rollup([1 => 40, 2 => 12])->summarize($project);

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

        $summary = $this->rollup([2 => 40, 3 => 8, 4 => 4])->summarize($project);

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

        $summary = $this->rollup([1 => 6])->summarize($project);

        self::assertSame([6, 0, 0], [$summary->lots[0]->enteredQuarters, $summary->lots[0]->remainingQuarters, $summary->lots[0]->overrunQuarters]);
        self::assertTrue($summary->isPartial());
    }

    public function testWhatIsLeftOnALeafFollowsItsProgressWhileItsOverrunFollowsItsEstimate(): void
    {
        $project = new Project()->setTitle('Kadence');
        $slow = $this->lot($project, 20, $this->user(), id: 1);
        $overrun = $this->lot($project, 10, $this->user(), id: 2);

        $summary = $this->rollup([1 => 52, 2 => 48], [
            1 => [$this->declaration($slow, 40, 40, 60)],
            2 => [$this->declaration($overrun, 80, 48, 12)],
        ])->summarize($project);

        [$slowSummary, $overrunSummary] = $summary->lots;
        self::assertSame([48, 0, 100], [$slowSummary->remainingQuarters, $slowSummary->overrunQuarters, $slowSummary->projectedQuarters]);
        self::assertSame([12, 8, 60], [$overrunSummary->remainingQuarters, $overrunSummary->overrunQuarters, $overrunSummary->projectedQuarters]);
        self::assertSame([60, 8], [$summary->remainingQuarters, $summary->overrunQuarters]);
        self::assertSame(40, $slowSummary->progressPercent());
        self::assertSame([20, 20], [$slowSummary->projectedGapQuarters(), $overrunSummary->projectedGapQuarters()]);
    }

    public function testAProgressWithdrawnAtZeroPercentLeavesTheEstimateInForce(): void
    {
        $project = new Project()->setTitle('Kadence');
        $lot = $this->lot($project, 20, $this->user(), id: 1);
        $withdrawn = $this->declaration($lot, 0, 40, null);
        $earlier = $this->declaration($lot, 40, 20, 30);

        $leaf = $this->rollup([1 => 40], [1 => [$withdrawn, $earlier]])->summarize($project)->lots[0];

        self::assertSame(40, $leaf->remainingQuarters);
        self::assertNull($leaf->projectedQuarters);
        self::assertSame($withdrawn, $leaf->progress);
        self::assertSame([$withdrawn, $earlier], $leaf->declarations);
        self::assertSame(50, $leaf->progressPercent(), 'Half the estimate entered.');
    }

    public function testALeafBackToEstimateKeepsItsDeclarationsButHasNoProgress(): void
    {
        $project = new Project()->setTitle('Kadence');
        $lot = $this->lot($project, null, $this->user(), id: 1);

        $declaration = $this->declaration($lot, 30, 0, 28);

        $leaf = $this->rollup([], [1 => [$declaration]])->summarize($project)->lots[0];

        self::assertSame([$declaration], $leaf->declarations);
        self::assertNull($leaf->progress);
        self::assertNull($leaf->projectedQuarters);
        self::assertNull($leaf->projectedGapQuarters());
        self::assertNull($leaf->progressPercent());
    }

    public function testSplitLotAndProjectWeighTheProgressOfTheirLeavesByTheirEstimate(): void
    {
        $project = new Project()->setTitle('Kadence');
        $split = $this->lot($project, null, null, id: 1);
        $declared = $this->lot($project, 20, $this->user(), $split, 2);
        $this->lot($project, 20, $this->user(), $split, 3);
        $this->lot($project, null, $this->user(), id: 4);

        $summary = $this->rollup([3 => 24, 4 => 8], [2 => [$this->declaration($declared, 50, 0, 40)]])->summarize($project);

        self::assertSame(40, $summary->lots[0]->progressPercent());
        self::assertSame(40, $summary->progressPercent());
        self::assertTrue($summary->isPartial());
        self::assertNull($summary->lots[1]->progressPercent(), 'A leaf to estimate has no progress.');
    }

    public function testATimeEnteredBeyondTheEstimateCountsAsAHundredPercent(): void
    {
        $project = new Project()->setTitle('Kadence');
        $split = $this->lot($project, null, null, id: 1);
        $this->lot($project, 10, $this->user(), $split, 2);
        $this->lot($project, 10, $this->user(), $split, 3);

        self::assertSame(50, $this->rollup([2 => 60])->summarize($project)->lots[0]->progressPercent());
    }

    /**
     * @param array<int, int>                         $quartersByLot     by lot id
     * @param array<int, non-empty-list<LotProgress>> $declarationsByLot by lot id, the latest first
     */
    private function rollup(array $quartersByLot = [], array $declarationsByLot = []): ProjectRollup
    {
        $timeEntryRepository = $this->createStub(TimeEntryRepository::class);
        $timeEntryRepository->method('sumQuartersByLot')->willReturn($quartersByLot);
        $lotProgressRepository = $this->createStub(LotProgressRepository::class);
        $lotProgressRepository->method('findForProjectByLot')->willReturn($declarationsByLot);

        return new ProjectRollup($timeEntryRepository, $lotProgressRepository);
    }

    /**
     * @param int<0, 100>      $percent
     * @param int<0, max>      $entered
     * @param int<0, max>|null $remaining
     */
    private function declaration(Lot $lot, int $percent, int $entered, ?int $remaining): LotProgress
    {
        return new LotProgress($lot, $this->user(), new \DateTimeImmutable('2026-10-02'), $percent, $entered, $remaining);
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
