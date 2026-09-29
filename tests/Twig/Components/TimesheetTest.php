<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Dto\LotInput;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Repository\TimeEntryRepository;
use App\Service\ProjectManager;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class TimesheetTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;
    use InteractsWithLiveComponents;

    protected function setUp(): void
    {
        self::mockTime('2026-09-30 10:00');
    }

    public function testRendersRowsTotalsAndDaySignals(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $lot = $this->createLot($project);
        $this->createTimeEntry($user, $lot, '2026-09-28', 4);
        $this->createTimeEntry($user, $lot, '2026-09-29', 2);

        $crawler = $this->component($user)->render()->crawler();

        self::assertCount(1, $crawler->filter('[data-test="timesheet-row"]'));
        self::assertStringContainsString($project->getTitle() . ' › ' . $lot->getTitle(), $crawler->filter('[data-test="timesheet-row"] th')->text());
        self::assertSame('1,5 j / 5 j', $crawler->filter('[data-test="week-total"]')->text());
        self::assertSame(
            [['true', 'false', 'false'], ['false', 'true', 'false'], ['false', 'false', 'true'], ['false', 'false', 'false'], ['false', 'false', 'false']],
            $crawler->filter('[data-test="day-header"]')->each(static fn (Crawler $header): array => [
                $header->attr('data-complete'), $header->attr('data-forgotten'), $header->attr('data-today'),
            ]),
        );
        self::assertCount(8, $crawler->filter('[data-test="timesheet-cell"][data-day="2026-10-01"] [data-test="quarter"]:disabled, [data-test="timesheet-cell"][data-day="2026-10-02"] [data-test="quarter"]:disabled'));
    }

    public function testClickingANotchRecordsItAndReclickingTheActiveOneEmptiesTheCell(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());
        $this->createTimeEntry($user, $lot, '2026-09-28', 1);
        $component = $this->component($user);

        $component->call('record', ['lot' => $lot->getId(), 'day' => '2026-09-29', 'quarters' => 3]);

        $bar = $component->render()->crawler()->filter('[data-test="timesheet-cell"][data-day="2026-09-29"] [data-test="quarter-bar"]');
        self::assertSame('3', $bar->attr('data-quarters'));
        self::assertSame('0', $bar->filter('[data-value="3"]')->attr('data-live-quarters-param'));
        self::assertSame([1, 3], $this->quartersOf($user));

        $component->call('record', ['lot' => $lot->getId(), 'day' => '2026-09-29', 'quarters' => 0]);
        self::assertSame([1], $this->quartersOf($user));
    }

    public function testNotchesBeyondTheDayCapAreDisabled(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $this->createTimeEntry($user, $this->createLot($project), '2026-09-28', 3);
        $this->createTimeEntry($user, $other = $this->createLot($project), '2026-09-22', 1);

        $bar = $this->component($user)->render()->crawler()
            ->filter('[data-test="timesheet-row"][data-lot="' . $other->getId() . '"] [data-test="timesheet-cell"][data-day="2026-09-28"] [data-test="quarter"]');

        self::assertSame([false, true, true, true], $bar->each(static fn (Crawler $notch): bool => null !== $notch->attr('disabled')));
    }

    public function testARefusedNotchShowsTheReasonAndLeavesTheCellUnchanged(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $this->createTimeEntry($user, $this->createLot($project), '2026-09-28', 4);
        $other = $this->createLot($project);
        $component = $this->component($user);
        $component->call('addLot', ['lot' => $other->getId()]);

        $component->call('record', ['lot' => $other->getId(), 'day' => '2026-09-28', 'quarters' => 1]);

        $crawler = $component->render()->crawler();
        self::assertSame('La journée du 28/09 est déjà complète.', $crawler->filter('[data-test="timesheet-error"]')->text());
        self::assertSame([4], $this->quartersOf($user));
    }

    public function testClickingALotSplitSinceTheGridOpenedExplainsWhyAndShowsTheSubLotThatTookItsTime(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());
        $this->createTimeEntry($user, $lot, '2026-09-28', 1);
        $input = LotInput::forSubLotOf($lot);
        $input->title = 'Premier sous-lot';
        $projectManager = self::getContainer()->get(ProjectManager::class);
        \assert($projectManager instanceof ProjectManager);
        $subLot = $projectManager->addSubLot($lot, $input);

        $component = $this->component($user);
        $component->call('record', ['lot' => $lot->getId(), 'day' => '2026-09-29', 'quarters' => 1]);

        $crawler = $component->render()->crawler();
        self::assertSame(\sprintf('« %s » est découpé en sous-lots : saisissez sur l\'un d\'eux.', $lot->getTitle()), $crawler->filter('[data-test="timesheet-error"]')->text());
        self::assertSame([(string) $subLot->getId()], $crawler->filter('[data-test="timesheet-row"]')->each(static fn (Crawler $row): ?string => $row->attr('data-lot')));
        self::assertSame([1], $this->quartersOf($user));
    }

    public function testAnAddedLineStaysUntilTheGridIsMountedAgain(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());
        $component = $this->component($user);
        self::assertCount(1, $component->render()->crawler()->filter('[data-test="timesheet-empty"]'));

        $component->call('addLot', ['lot' => $lot->getId()]);
        self::assertCount(1, $component->render()->crawler()->filter('[data-test="timesheet-row"][data-lot="' . $lot->getId() . '"]'));

        self::assertCount(0, $this->component($user)->render()->crawler()->filter('[data-test="timesheet-row"]'));
    }

    public function testSearchProposesLeavesOnlyAndNotTheShownOnes(): void
    {
        $user = $this->createUser();
        $project = $this->createProject(uniqid('Recherche ', true));
        $split = $this->createLot($project);
        $subLot = $this->createLot($project, parent: $split);
        $shown = $this->createLot($project);
        $this->createTimeEntry($user, $shown, '2026-09-28', 1);

        $results = $this->component($user)->set('query', (string) $project->getTitle())->render()->crawler()->filter('[data-test="add-line-result"]');

        self::assertSame([(string) $subLot->getId()], $results->each(static fn (Crawler $result): ?string => $result->attr('data-lot')));
    }

    public function testShowDayChangesTheDayShownOnSmallScreens(): void
    {
        $component = $this->component($this->createUser());
        self::assertSame('2026-09-30', $this->selectedTab($component));

        $component->call('showDay', ['index' => 4]);

        self::assertSame('2026-10-02', $this->selectedTab($component));
    }

    public function testNeverShowsTheTimeOfSomeoneElse(): void
    {
        $user = $this->createUser();
        $this->createTimeEntry($this->createUser(), $this->createLot($this->createProject()), '2026-09-28', 4);

        $crawler = $this->component($user)->render()->crawler();

        self::assertCount(0, $crawler->filter('[data-test="timesheet-row"]'));
        self::assertSame('0 j / 5 j', $crawler->filter('[data-test="week-total"]')->text());
    }

    public function testRecordingOutsideTheShownWeekIsRefused(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());

        $this->expectException(NotFoundHttpException::class);

        $this->component($user)->call('record', ['lot' => $lot->getId(), 'day' => '2026-09-21', 'quarters' => 1]);
    }

    private function component(User $user): TestLiveComponent
    {
        return $this->createLiveComponent('Timesheet', ['week' => '2026-W40'])->actingAs($user);
    }

    private function selectedTab(TestLiveComponent $component): ?string
    {
        return $component->render()->crawler()->filter('[data-test="day-tab"][aria-selected="true"]')->attr('data-day');
    }

    /**
     * @return list<int>
     */
    private function quartersOf(User $user): array
    {
        $repository = self::getContainer()->get(TimeEntryRepository::class);
        \assert($repository instanceof TimeEntryRepository);
        $entries = $repository->findForUserBetween($user, new \DateTimeImmutable('2026-09-28'), new \DateTimeImmutable('2026-10-02'));
        usort($entries, static fn (TimeEntry $a, TimeEntry $b): int => $a->getDay() <=> $b->getDay());

        return array_map(static fn (TimeEntry $entry): int => $entry->getQuarters(), $entries);
    }
}
