<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\HalfDay;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Enum\Type\TimeOffType;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesTimeOff;
use App\Tests\Support\CreatesUsers;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class TimeOffControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesTimeOff;
    use CreatesUsers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::mockTime('2026-10-12 10:00');
        $this->client = self::createClient();
    }

    public function testTheTabsFollowWhatThePersonMayDo(): void
    {
        $prod = $this->createUser();
        $manager = $this->createUser(Role::Lead);
        $this->createUser()->setManager($manager);
        $this->entityManager()->flush();

        self::assertSame(['mine', 'absences'], $this->tabsOf($prod));
        self::assertSame(['mine', 'inbox', 'absences'], $this->tabsOf($manager));
        self::assertSame(['mine', 'inbox', 'absences'], $this->tabsOf($this->fixtureUser('thomas.cerqueus@anyti.me')));
    }

    public function testMyTimeOffListsMyBalancesAndMyRequests(): void
    {
        $user = $this->createUser();
        $this->createEntitlement($user, TimeOffType::PaidLeave, 2026, 50);
        $this->createTimeOff($user, '2026-11-09', '2026-11-13');
        $refused = $this->createTimeOff($user, '2026-12-21', '2026-12-21', TimeOffStatus::Refused);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/conges');

        self::assertResponseIsSuccessful();
        self::assertSame('21 j', $crawler->filter('[data-test="time-off-balance"][data-type="cp"] [data-test="time-off-balance-remaining"]')->text());
        self::assertCount(2, $crawler->filter('[data-test="time-off-request-row"]'));
        self::assertSame('Motif : Refus de test', $crawler->filter(\sprintf('[data-test="time-off-request-row"][data-id="%d"] [data-test="time-off-refusal-reason"]', $refused->getId()))->text());
    }

    public function testALeadWithNoOneToDecideForHasNoInbox(): void
    {
        [, $requester] = $this->team();
        $this->createTimeOff($requester, '2026-11-16', '2026-11-20', TimeOffStatus::Pending);
        $lead = $this->createUser(Role::Lead);
        $this->client->loginUser($lead);

        $this->client->request('GET', '/conges/a-valider');
        self::assertResponseStatusCodeSame(403);
    }

    public function testThePersonCancelsTimeOffBeforeItStartsButNotOnceStarted(): void
    {
        $user = $this->createUser();
        $coming = $this->createTimeOff($user, '2026-10-19', '2026-10-23');
        $started = $this->createTimeOff($user, '2026-10-12', '2026-10-16');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/conges');
        self::assertCount(0, $crawler->filter(\sprintf('[data-test="time-off-request-row"][data-id="%d"] [data-test="time-off-cancel"]', $started->getId())));
        $this->client->submit($crawler->filter(\sprintf('[data-test="time-off-request-row"][data-id="%d"] [data-test="time-off-cancel"]', $coming->getId()))->form());

        self::assertResponseRedirects('/conges');
        self::assertSame(TimeOffStatus::Cancelled, $this->reload($coming)->getStatus());

        $this->client->request('POST', '/conges/' . $started->getId() . '/annuler');
        self::assertResponseStatusCodeSame(403);
    }

    public function testCancellingComesBackOnlyToAPathOfThisSite(): void
    {
        $user = $this->createUser();
        $first = $this->createTimeOff($user, '2026-10-19', '2026-10-19');
        $second = $this->createTimeOff($user, '2026-10-20', '2026-10-20');
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/conges');
        $form = static fn (TimeOffRequest $request) => $crawler->filter(\sprintf('[data-test="time-off-request-row"][data-id="%d"] [data-test="time-off-cancel"]', $request->getId()))->form();

        $this->client->submit($form($first), ['back' => '/personnes/' . $user->getId()]);
        self::assertResponseRedirects('/personnes/' . $user->getId());

        $this->client->submit($form($second), ['back' => '/\\exemple.com']);
        self::assertResponseRedirects('/conges');
    }

    public function testTheInboxNamesNoTeamForAPersonWithoutManager(): void
    {
        $requester = $this->createUser();
        $request = $this->createTimeOff($requester, '2026-11-16', '2026-11-16', TimeOffStatus::Pending);
        $this->client->loginUser($this->fixtureUser('thomas.cerqueus@anyti.me'));

        $crawler = $this->client->request('GET', '/conges/a-valider');

        $item = $crawler->filter(\sprintf('[data-test="time-off-inbox-item"][data-id="%d"]', $request->getId()));
        self::assertCount(1, $item);
        self::assertCount(0, $item->filter('[data-test="time-off-inbox-colleagues"]'));
    }

    public function testTheQueriesOfTheTimeOffPagesDoNotGrowWithTheRequests(): void
    {
        $manager = $this->createUser(Role::Lead);
        $few = $this->createUser()->setManager($manager);
        $many = $this->createUser()->setManager($manager);
        $this->entityManager()->flush();
        $this->createEntitlement($many, TimeOffType::PaidLeave, 2026, 50);
        $this->createTimeOff($few, '2026-11-16', '2026-11-16', TimeOffStatus::Pending);
        foreach (range(0, 9) as $week) {
            $monday = new \DateTimeImmutable('2026-11-23')->modify(\sprintf('+%d weeks', $week));
            $this->createTimeOff($many, $monday->format('Y-m-d'), $monday->modify('+1 day')->format('Y-m-d'), TimeOffStatus::Approved, 0 === $week % 2 ? TimeOffType::PaidLeave : TimeOffType::Rtt);
            $this->createTimeOff($many, $monday->modify('+3 days')->format('Y-m-d'), $monday->modify('+3 days')->format('Y-m-d'), TimeOffStatus::Pending);
        }
        $other = $this->createUser(Role::Lead);
        $this->createTimeOff($this->createUser()->setManager($other), '2026-12-07', '2026-12-07', TimeOffStatus::Pending);
        $this->queryCount($few, '/conges');

        self::assertSame($this->queryCount($few, '/conges'), $this->queryCount($many, '/conges'));
        self::assertSame($this->queryCount($other, '/conges/a-valider'), $this->queryCount($manager, '/conges/a-valider'), 'One pending request or eleven.');
    }

    public function testTheAbsencesShowTheTypeToTheManagerOnly(): void
    {
        [$manager, $requester] = $this->team();
        $this->createTimeOff($requester, '2026-11-09', '2026-11-13');
        $this->createTimeOff($requester, '2026-11-16', '2026-11-16', TimeOffStatus::Pending, TimeOffType::Rtt, HalfDay::Morning, HalfDay::Morning);
        $cell = static fn (string $day): string => \sprintf('[data-test="absences-row"][data-person="%d"] [data-test="absences-cell"][data-day="%s"]', $requester->getId(), $day);

        $this->client->loginUser($this->createUser());
        $crawler = $this->client->request('GET', '/conges/absences/2026-11');
        self::assertSame('whole', $crawler->filter($cell('2026-11-10'))->attr('data-state'));
        self::assertSame('holiday', $crawler->filter($cell('2026-11-11'))->attr('data-state'));
        self::assertSame('Absent', $crawler->filter($cell('2026-11-10'))->attr('title'));
        self::assertSame('none', $crawler->filter($cell('2026-11-16'))->attr('data-state'), 'A pending request is not shown to others.');

        $this->client->loginUser($manager);
        $crawler = $this->client->request('GET', '/conges/absences/2026-11');
        self::assertSame('Congé · CP', $crawler->filter($cell('2026-11-10'))->attr('title'));
        self::assertSame('half', $crawler->filter($cell('2026-11-16'))->attr('data-state'));
    }

    public function testTheEntitlementsAreForTheDirectionOnly(): void
    {
        foreach (['olivier.thomas@anyti.me', 'antoine.leroy@anyti.me'] as $email) {
            $this->client->loginUser($this->fixtureUser($email));
            $this->client->request('GET', '/conges/compteurs');
            self::assertResponseStatusCodeSame(403, $email);
        }
    }

    public function testTheDirectionOpensTheEntitlementsOfAPeriod(): void
    {
        $person = $this->createUser();
        $this->client->loginUser($this->fixtureUser('thomas.cerqueus@anyti.me'));

        $crawler = $this->client->request('GET', '/conges/compteurs/rtt/2026');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Droits à congés');
        self::assertPageTitleSame('Droits à congés · Kadence');
        self::assertSelectorNotExists('[data-test="time-off-tabs"]');
        self::assertSame('RTT 2026', $crawler->filter('[data-test="entitlements-period"]')->text());
        self::assertCount(1, $crawler->filter(\sprintf('[data-test="entitlements-form"] [name="time_off_entitlements[days][%d]"]', $person->getId())));
        self::assertSame('/conges/compteurs/cp/2026', $crawler->filter('[data-test="entitlements-type-cp"]')->attr('href'));
    }

    /**
     * @return array{User, User} a manager and a person they manage
     */
    private function team(): array
    {
        $manager = $this->createUser(Role::Lead);
        $requester = $this->createUser()->setManager($manager);
        $this->entityManager()->flush();

        return [$manager, $requester];
    }

    /**
     * @return list<string>
     */
    private function tabsOf(User $user): array
    {
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/conges');

        return $crawler->filter('[data-test^="time-off-tab-"]')->each(static fn ($tab): string => substr((string) $tab->attr('data-test'), \strlen('time-off-tab-')));
    }

    private function reload(TimeOffRequest $request): TimeOffRequest
    {
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(TimeOffRequest::class, $request->getId());
        \assert($reloaded instanceof TimeOffRequest);

        return $reloaded;
    }

    private function queryCount(User $user, string $url): int
    {
        $this->client->loginUser($user);
        $this->client->enableProfiler();
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        $profile = $this->client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    private function fixtureUser(string $email): User
    {
        $userRepository = self::getContainer()->get(UserRepository::class);
        \assert($userRepository instanceof UserRepository);
        $user = $userRepository->findOneByEmail($email);
        self::assertNotNull($user);

        return $user;
    }
}
