<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Enum\Type\HolidayCalendar;
use App\Model\Timesheet\TimesheetCell;
use App\Model\Timesheet\TimesheetDay;
use App\Model\Timesheet\TimesheetRow;
use App\Model\Timesheet\WeekGrid;
use App\Model\Week;
use App\Service\TimesheetBuilder;
use App\Tests\Support\CreatesFavorites;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TimesheetBuilderTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesFavorites;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    protected function setUp(): void
    {
        self::mockTime('2026-09-30 10:00');
    }

    public function testRowsAreTheLeavesOfThisWeekAndThePreviousOnePlusTheAddedOnesInTreeOrder(): void
    {
        $user = $this->createUser();
        $zeta = $this->createProject(uniqid('Zêta ', true));
        $alpha = $this->createProject(uniqid('Alpha ', true));
        $zetaLot = $this->createLot($zeta);
        $split = $this->createLot($alpha);
        $firstSubLot = $this->createLot($alpha, parent: $split);
        $secondSubLot = $this->createLot($alpha, parent: $split);
        $alphaLot = $this->createLot($alpha);
        $older = $this->createLot($alpha);
        $this->createTimeEntry($user, $zetaLot, '2026-09-28', 2);
        $this->createTimeEntry($user, $secondSubLot, '2026-09-22', 1);
        $this->createTimeEntry($user, $alphaLot, '2026-09-29', 1);
        $this->createTimeEntry($user, $older, '2026-09-18', 1);

        $grid = $this->build($user, [(int) $firstSubLot->getId(), (int) $split->getId()]);

        self::assertSame(
            [$firstSubLot->getId(), $secondSubLot->getId(), $alphaLot->getId(), $zetaLot->getId()],
            array_map(static fn (TimesheetRow $row): ?int => $row->lot->getId(), $grid->rows),
        );
        self::assertSame([0, 0, 0, 0, 0], $this->quartersOf($grid->rows[1]), 'the previous week is not shown');
        self::assertSame([2, 0, 0, 0, 0], $this->quartersOf($grid->rows[3]));
    }

    public function testFavoritesComeFirstInTreeOrderThenTheOtherRows(): void
    {
        $user = $this->createUser();
        $zeta = $this->createProject(uniqid('Zêta ', true));
        $alpha = $this->createProject(uniqid('Alpha ', true));
        $zetaFavorite = $this->createLot($zeta);
        $split = $this->createLot($alpha);
        $subLotFavorite = $this->createLot($alpha, parent: $split);
        $alphaLot = $this->createLot($alpha);
        $added = $this->createLot($alpha);
        $this->createFavorite($user, $zetaFavorite);
        $this->createFavorite($user, $subLotFavorite);
        $this->createTimeEntry($user, $alphaLot, '2026-09-28', 1);

        $grid = $this->build($user, [(int) $added->getId()]);

        self::assertSame(
            [[$subLotFavorite->getId(), true], [$zetaFavorite->getId(), true], [$alphaLot->getId(), false], [$added->getId(), false]],
            array_map(static fn (TimesheetRow $row): array => [$row->lot->getId(), $row->favorite], $grid->rows),
        );
    }

    public function testAFavoriteWithoutTimeIsARowOfAnyWeekPastOrFuture(): void
    {
        $user = $this->createUser();
        $favorite = $this->createLot($this->createProject());
        $this->createFavorite($user, $favorite);

        foreach (['2026-W30', '2026-W40', '2026-W45'] as $week) {
            $grid = $this->build($user, [], $week);

            self::assertCount(1, $grid->rows, $week);
            self::assertSame($favorite, $grid->rows[0]->lot);
            self::assertTrue($grid->rows[0]->favorite);
        }
        self::assertSame([true, true, true, true, true], array_map(static fn (TimesheetCell $cell): bool => $cell->locked, $grid->rows[0]->cells));
    }

    public function testAFavoriteCarryingTimeOrAddedAgainIsShownOnce(): void
    {
        $user = $this->createUser();
        $favorite = $this->createLot($this->createProject());
        $this->createFavorite($user, $favorite);
        $this->createTimeEntry($user, $favorite, '2026-09-29', 3);

        $grid = $this->build($user, [(int) $favorite->getId()]);

        self::assertCount(1, $grid->rows);
        self::assertTrue($grid->rows[0]->favorite);
        self::assertSame([0, 3, 0, 0, 0], $this->quartersOf($grid->rows[0]));
    }

    public function testTheFavoritesOfAnotherPersonAreNotShown(): void
    {
        $this->createFavorite($this->createUser(), $this->createLot($this->createProject()));

        self::assertSame([], $this->build($this->createUser())->rows);
    }

    public function testNotchesAreLimitedByWhatTheDayStillAllows(): void
    {
        $user = $this->createUser();
        $project = $this->createProject();
        $entered = $this->createLot($project);
        $other = $this->createLot($project);
        $this->createTimeEntry($user, $entered, '2026-09-28', 3);

        $grid = $this->build($user, [(int) $other->getId()]);

        self::assertSame(4, $this->monday($grid, $entered->getId())->maxSelectable);
        self::assertSame(1, $this->monday($grid, $other->getId())->maxSelectable);
        self::assertSame(4, $grid->rows[1]->cells[1]->maxSelectable);
    }

    public function testNotchesAreLimitedByTheWeeklyMaximumButNeverBelowTheCurrentValue(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());
        $other = $this->createLot($lot->getProject());
        foreach (['2026-09-28', '2026-09-29', '2026-09-30'] as $day) {
            $this->createTimeEntry($user, $lot, $day, 4);
        }
        $this->createWeeklyMax($user, '2026-09-28', 10);

        $grid = $this->build($user, [(int) $other->getId()]);

        self::assertSame(12, $grid->quarters);
        self::assertSame(10, $grid->maxQuarters);
        self::assertTrue($grid->isComplete());
        self::assertSame(4, $this->monday($grid, $lot->getId())->maxSelectable);
        self::assertSame(0, $this->monday($grid, $other->getId())->maxSelectable);
    }

    public function testFutureDaysAreLockedAndPastIncompleteDaysForgottenUntilTheWeekReachesItsMaximum(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());
        $this->createTimeEntry($user, $lot, '2026-09-28', 4);
        $this->createTimeEntry($user, $lot, '2026-09-29', 2);

        $grid = $this->build($user);

        self::assertSame(
            [[true, false, false], [false, true, false], [false, false, true], [false, false, false], [false, false, false]],
            array_map(static fn (TimesheetDay $day): array => [$day->isComplete(), $day->forgotten, $day->today], $grid->days),
        );
        self::assertSame([false, false, false, true, true], array_map(static fn (TimesheetCell $cell): bool => $cell->locked, $grid->rows[0]->cells));
        self::assertFalse($grid->rows[0]->cells[3]->isSelectable(1));

        $this->createWeeklyMax($user, '2026-09-28', 6);
        self::assertSame([false, false, false, false, false], array_map(static fn (TimesheetDay $day): bool => $day->forgotten, $this->build($user)->days));
    }

    public function testHolidaysOfThePersonsCalendarAreLockedNamedNeverForgottenAndLeftOutOfTheWeeklyMaximum(): void
    {
        $lot = $this->createLot($this->createProject());
        $fullTime = $this->createUser();
        $partTime = $this->createUser();
        $this->createWeeklyMax($partTime, '2026-07-13', 18);
        $this->createTimeEntry($fullTime, $lot, '2026-07-13', 4);

        $grid = $this->build($fullTime, [], '2026-W29');

        self::assertSame([null, 'Fête nationale', null, null, null], array_map(static fn (TimesheetDay $day): ?string => $day->holiday, $grid->days));
        self::assertSame([false, false, true, true, true], array_map(static fn (TimesheetDay $day): bool => $day->forgotten, $grid->days));
        self::assertSame([false, true, false, false, false], array_map(static fn (TimesheetCell $cell): bool => $cell->locked, $grid->rows[0]->cells));
        self::assertFalse($grid->rows[0]->cells[1]->isSelectable(1));
        self::assertSame(16, $grid->maxQuarters);
        self::assertSame(16, $this->build($partTime, [], '2026-W29')->maxQuarters);
    }

    public function testAHolidayOfTheOtherCalendarIsAnOrdinaryDay(): void
    {
        $belgian = $this->createUser(holidayCalendar: HolidayCalendar::Belgium);
        $lot = $this->createLot($this->createProject());

        $grid = $this->build($belgian, [(int) $lot->getId()], '2026-W29');

        self::assertSame([null, null, null, null, null], array_map(static fn (TimesheetDay $day): ?string => $day->holiday, $grid->days));
        self::assertSame([false, false, false, false, false], array_map(static fn (TimesheetCell $cell): bool => $cell->locked, $grid->rows[0]->cells));
        self::assertSame(20, $grid->maxQuarters);
    }

    /**
     * @param list<int> $addedLotIds
     */
    private function build(User $user, array $addedLotIds = [], string $week = '2026-W40'): WeekGrid
    {
        $builder = self::getContainer()->get(TimesheetBuilder::class);
        \assert($builder instanceof TimesheetBuilder);

        return $builder->build($user, Week::fromIso($week), $addedLotIds);
    }

    /**
     * @return list<int>
     */
    private function quartersOf(TimesheetRow $row): array
    {
        return array_map(static fn (TimesheetCell $cell): int => $cell->quarters, $row->cells);
    }

    private function monday(WeekGrid $grid, ?int $lotId): TimesheetCell
    {
        foreach ($grid->rows as $row) {
            if ($row->lot->getId() === $lotId) {
                return $row->cells[0];
            }
        }

        self::fail('The lot has a row.');
    }
}
