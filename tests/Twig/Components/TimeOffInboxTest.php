<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Tests\Support\CreatesTimeOff;
use App\Tests\Support\CreatesUsers;
use App\Tests\Support\ReadsInPlaceFlashes;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class TimeOffInboxTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesTimeOff;
    use CreatesUsers;
    use InteractsWithLiveComponents;
    use ReadsInPlaceFlashes;

    protected function setUp(): void
    {
        self::mockTime('2026-10-12 10:00');
    }

    public function testTheManagerApprovesInPlaceAndTheTabCountFollows(): void
    {
        [$manager, $requester] = $this->team();
        $request = $this->createTimeOff($requester, '2026-11-16', '2026-11-20', TimeOffStatus::Pending);
        $other = $this->createTimeOff($requester, '2026-12-07', '2026-12-07', TimeOffStatus::Pending);
        $component = $this->component($manager);
        self::assertSame('2', $component->render()->crawler()->filter('[data-test="time-off-inbox-count"]')->text());

        $rendered = $component->call('approve', ['request' => $request->getId()])->render();

        self::assertSame(TimeOffStatus::Approved, $this->reload($request)->getStatus());
        self::assertEmailCount(1);
        self::assertSame(['type' => 'success', 'message' => \sprintf('Le congé de %s est validé.', $requester->getFirstName())], self::flashOf($component, $rendered));
        self::assertSame([(string) $other->getId()], $rendered->crawler()->filter('[data-test="time-off-inbox-item"]')->each(static fn (Crawler $item): string => (string) $item->attr('data-id')));
        self::assertSame('1', $rendered->crawler()->filter('[data-test="time-off-inbox-count"]')->text());
        self::assertCount(1, $rendered->crawler()->filter('[data-test="time-off-tab-inbox"][aria-current="page"]'), 'The inbox tab stays the current one.');
    }

    public function testARefusalNeedsAReasonAndKeepsWhatWasTyped(): void
    {
        [$manager, $requester] = $this->team();
        $request = $this->createTimeOff($requester, '2026-11-16', '2026-11-20', TimeOffStatus::Pending);
        $component = $this->component($manager);
        $field = 'reasons.' . $request->getId();

        $rendered = $component->set($field, '  ')->call('refuse', ['request' => $request->getId()])->render();

        self::assertSame(TimeOffStatus::Pending, $this->reload($request)->getStatus());
        self::assertCount(1, $rendered->crawler()->filter(\sprintf('[data-id="%d"] [data-test="time-off-refuse-error"]', $request->getId())));

        $rendered = $component->set($field, 'Mise en production')->call('refuse', ['request' => $request->getId()])->render();

        $refused = $this->reload($request);
        self::assertSame(TimeOffStatus::Refused, $refused->getStatus());
        self::assertSame('Mise en production', $refused->getRefusalReason());
        self::assertCount(1, $rendered->crawler()->filter('[data-test="time-off-inbox-empty"]'));
    }

    public function testAManagerCannotDecideForSomeoneElsesTeam(): void
    {
        [$manager, $report] = $this->team();
        [, $stranger] = $this->team();
        $theirs = $this->createTimeOff($stranger, '2026-11-16', '2026-11-20', TimeOffStatus::Pending);
        $mine = $this->createTimeOff($report, '2026-11-23', '2026-11-23', TimeOffStatus::Pending);
        $component = $this->component($manager);
        self::assertCount(1, $component->render()->crawler()->filter(\sprintf('[data-test="time-off-inbox-item"][data-id="%d"]', $mine->getId())));

        $this->expectException(AccessDeniedException::class);
        $component->call('approve', ['request' => $theirs->getId()]);
    }

    private function component(User $viewer): TestLiveComponent
    {
        return $this->createLiveComponent('TimeOffInbox')->actingAs($viewer);
    }

    /**
     * @return array{User, User} a manager and a person they manage
     */
    private function team(): array
    {
        $manager = $this->createUser(Role::Lead);

        return [$manager, $this->reportOf($manager)];
    }

    private function reportOf(User $manager): User
    {
        $report = $this->createUser()->setManager($manager);
        $this->entityManager()->flush();

        return $report;
    }

    private function reload(TimeOffRequest $request): TimeOffRequest
    {
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(TimeOffRequest::class, $request->getId());
        \assert($reloaded instanceof TimeOffRequest);

        return $reloaded;
    }
}
