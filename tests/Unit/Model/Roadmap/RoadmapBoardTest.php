<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Roadmap;

use App\Entity\Lot;
use App\Entity\Project;
use App\Enum\Type\RoadmapSignal;
use App\Model\Roadmap\Roadmap;
use App\Model\Roadmap\RoadmapBoard;
use App\Model\Roadmap\RoadmapCounter;
use App\Model\Roadmap\RoadmapRow;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Week;
use PHPUnit\Framework\TestCase;

final class RoadmapBoardTest extends TestCase
{
    public function testCountsTheLeavesAwaitingEachSignalAndTheirProjects(): void
    {
        $nova = new Project()->setTitle('Nova');
        $split = new Lot($nova)->setTitle('Portail');
        $orion = new Project()->setTitle('Orion');
        $board = RoadmapBoard::of($this->roadmap(
            new RoadmapRow($nova, null, children: [
                $this->leaf($nova, 'Socle', [RoadmapSignal::Overrun]),
                new RoadmapRow($nova, $split, children: [$this->leaf($nova, 'Maquettes', [RoadmapSignal::Overrun, RoadmapSignal::ToReplan], $split)]),
            ]),
            new RoadmapRow($orion, null, children: [$this->leaf($orion, 'Recette', [RoadmapSignal::Overrun])]),
        ));

        self::assertEquals(['Dérives' => [
            new RoadmapCounter(RoadmapSignal::Overrun, 3, 2),
            new RoadmapCounter(RoadmapSignal::ToReplan, 1, 1),
        ]], $board->families);
    }

    public function testAFinishedLeafIsNeverCountedWhateverItsOtherSignals(): void
    {
        $nova = new Project()->setTitle('Nova');
        $board = RoadmapBoard::of($this->roadmap(new RoadmapRow($nova, null, children: [
            $this->leaf($nova, 'Socle', [RoadmapSignal::Overrun, RoadmapSignal::Completed]),
            $this->leaf($nova, 'Recette', [RoadmapSignal::EstimateReached, RoadmapSignal::TeamToReview]),
        ])));

        self::assertTrue($board->isEmpty());
    }

    public function testFamiliesComeInTheirOrderWithTheirSignalsInOrderAndNothingAtZero(): void
    {
        $nova = new Project()->setTitle('Nova');
        $board = RoadmapBoard::of($this->roadmap(new RoadmapRow($nova, null, children: [
            $this->leaf($nova, 'Doc', [RoadmapSignal::ProgressToRefresh]),
            $this->leaf($nova, 'Socle', [RoadmapSignal::LateStart, RoadmapSignal::ToEstimate]),
            $this->leaf($nova, 'Recette', [RoadmapSignal::TeamToReview]),
        ])));

        self::assertSame(['Dérives', 'Planification', 'Avancement'], array_keys($board->families));
        self::assertSame(
            [[RoadmapSignal::TeamToReview], [RoadmapSignal::ToEstimate, RoadmapSignal::LateStart], [RoadmapSignal::ProgressToRefresh]],
            array_values(array_map(static fn (array $counters): array => array_map(static fn (RoadmapCounter $counter): RoadmapSignal => $counter->signal, $counters), $board->families)),
        );
    }

    public function testProjectSignalsHaveNoCounter(): void
    {
        $nova = new Project()->setTitle('Nova');
        $board = RoadmapBoard::of($this->roadmap(new RoadmapRow($nova, null, signals: [RoadmapSignal::Unsplit])));

        self::assertTrue($board->isEmpty());
        self::assertNotContains(RoadmapSignal::Unsplit, RoadmapBoard::signals());
        self::assertNotContains(RoadmapSignal::PartialPlanning, RoadmapBoard::signals());
    }

    public function testEverySignalOfTheBoardNamesItsActionAndNoOtherDoes(): void
    {
        foreach (RoadmapSignal::cases() as $signal) {
            self::assertSame(\in_array($signal, RoadmapBoard::signals(), true), null !== $signal->action(), $signal->value);
        }
    }

    private function roadmap(RoadmapRow ...$projects): Roadmap
    {
        return new Roadmap(RoadmapWindow::around(Week::fromIso('2026-W42')), new \DateTimeImmutable('2026-10-15'), array_values($projects));
    }

    /**
     * @param non-empty-string    $title
     * @param list<RoadmapSignal> $signals
     */
    private function leaf(Project $project, string $title, array $signals, ?Lot $parent = null): RoadmapRow
    {
        return new RoadmapRow($project, new Lot($project, $parent)->setTitle($title), signals: $signals);
    }
}
