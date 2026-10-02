<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model\Person;

use App\Entity\Lot;
use App\Entity\Project;
use App\Enum\Type\HolidayCalendar;
use App\Model\Person\LoadSpan;
use App\Model\Person\PersonLeafRow;
use App\Model\Person\PersonLoad;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Schedule\DailyCapacity;
use PHPUnit\Framework\TestCase;

final class PersonLoadTest extends TestCase
{
    private const int ALICE = 1;

    public function testSameLoadOnWorkingDaysInARowMakesOneSpanAcrossTheWeekend(): void
    {
        $loads = [
            ...$this->days(['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-12', '2026-10-13', '2026-10-14'], 50),
            ...$this->days(['2026-10-15', '2026-10-16'], 125),
            ...$this->days(['2026-10-20'], 50),
        ];

        $load = $this->load($loads);

        self::assertSame(
            [['2026-10-05', '2026-10-14', 50, false], ['2026-10-15', '2026-10-16', 125, true], ['2026-10-20', '2026-10-20', 50, false]],
            array_map(static fn (LoadSpan $span): array => [$span->from->format('Y-m-d'), $span->to->format('Y-m-d'), $span->percent, $span->isOverload()], $load->spans),
            'A working day without load (19/10) cuts the span; a change of load does too.',
        );
    }

    public function testNextDayIsTheFirstWorkingDayAfterTodayWithItsLoad(): void
    {
        $load = $this->load($this->days(['2026-10-05'], 50), '2026-10-02');

        self::assertSame('2026-10-05', $load->nextDay?->format('Y-m-d'), 'The weekend is skipped.');
        self::assertSame(50, $load->nextDayPercent);
    }

    public function testNextDaySkipsAHolidayOfTheCalendarOfThePerson(): void
    {
        $load = $this->load([], '2026-11-10', holidays: ['2026-11-11' => true]);

        self::assertSame('2026-11-12', $load->nextDay?->format('Y-m-d'));
        self::assertSame(0, $load->nextDayPercent);
    }

    public function testFreeFromIsTheFirstWorkingDayAfterTheLastDayLoaded(): void
    {
        self::assertSame('2026-10-27', $this->load($this->days(['2026-10-26'], 50))->freeFrom?->format('Y-m-d'));
        self::assertSame('2026-10-19', $this->load($this->days(['2026-10-16'], 50))->freeFrom?->format('Y-m-d'), 'From a Friday, the weekend is skipped.');
    }

    public function testFreeFromIsUnknownAsLongAsALeafOfTheirTeamsHasNoEnd(): void
    {
        $unknown = new PersonLeafRow(new Lot(new Project()->setTitle('Mobile'))->setTitle('Login'), 50, null, null, null, null, null, []);

        $load = $this->load($this->days(['2026-10-26'], 50), unknownEnds: [$unknown]);

        self::assertNull($load->freeFrom);
        self::assertSame([$unknown], $load->unknownEnds);
    }

    public function testNoLoadLeavesNoSpanAndNoDate(): void
    {
        $load = $this->load([]);

        self::assertSame([], $load->spans);
        self::assertNull($load->freeFrom);
        self::assertSame([], $load->unknownEnds);
    }

    /**
     * @param array<string, int>  $loads
     * @param array<string, true> $holidays
     * @param list<PersonLeafRow> $unknownEnds
     */
    private function load(array $loads, string $today = '2026-10-02', array $holidays = [], array $unknownEnds = []): PersonLoad
    {
        $capacity = new DailyCapacity([self::ALICE => [HolidayCalendar::France, true]], [], [HolidayCalendar::France->value => $holidays]);
        $window = RoadmapWindow::spanning(new \DateTimeImmutable('2026-09-28'), new \DateTimeImmutable('2026-11-15'));

        return PersonLoad::of($loads, $capacity, self::ALICE, new \DateTimeImmutable($today), $window, $unknownEnds);
    }

    /**
     * @param list<string> $days
     *
     * @return array<string, int>
     */
    private function days(array $days, int $percent): array
    {
        return array_fill_keys($days, $percent);
    }
}
