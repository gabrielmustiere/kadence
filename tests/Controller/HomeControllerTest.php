<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Lot;
use App\Entity\User;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesTimeOff;
use App\Tests\Support\CreatesUsers;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class HomeControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesTimeOff;
    use CreatesUsers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::mockTime('2026-10-14 10:00');
        $this->client = self::createClient();
    }

    public function testAMemberOfProdSeesTheirTimesheetAndTheirTimeOffOnly(): void
    {
        $user = $this->createUser();
        $this->createTimeOff($user, '2026-12-14', '2026-12-14', TimeOffStatus::Pending);
        $other = $this->createUser();
        $this->createTimeOff($other, '2026-12-15', '2026-12-15', TimeOffStatus::Pending);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Accueil');
        self::assertPageTitleSame('Accueil · Kadence');
        self::assertSame(['home-section-timesheet', 'home-section-my-time-off'], $this->sections($crawler));
        self::assertCount(1, $crawler->filter('[data-test="home-my-request"]'), 'Only their own request.');
        self::assertSame('/conges', $crawler->filter('[data-test="home-my-request"] a')->attr('href'));
        self::assertSelectorNotExists('[data-test="home-admin"], [data-test="home-roadmap"], [data-test="home-account"]');
    }

    public function testAForgottenDayLeadsToItsWeek(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/');

        $monday = $crawler->filter('[data-test="home-forgotten-day"][data-day="2026-10-05"] a');
        self::assertSame('/saisie/2026-W41', $monday->attr('href'));
        self::assertStringContainsString('05/10/2026', $monday->text());
        self::assertSame('/saisie/2026-W42', $crawler->filter('[data-test="home-forgotten-day"][data-day="2026-10-13"] a')->attr('href'));
        self::assertCount(0, $crawler->filter('[data-test="home-forgotten-day"][data-day="2026-10-14"]'), 'Not today.');
    }

    public function testAManagerSeesTheRequestsToDecideOnLeadingToTheInbox(): void
    {
        $manager = $this->createUser();
        $report = $this->createUser()->setManager($manager)->setFirstName('Zoé');
        $this->entityManager()->flush();
        $request = $this->createTimeOff($report, '2026-11-16', '2026-11-20', TimeOffStatus::Pending);
        $this->client->loginUser($manager);

        $crawler = $this->client->request('GET', '/');

        $row = $crawler->filter(\sprintf('[data-test="home-to-decide"][data-id="%d"]', $request->getId()));
        self::assertStringContainsString('Zoé User', $row->text());
        self::assertStringContainsString('CP', $row->text());
        self::assertStringContainsString('16/11/2026', $row->text());
        self::assertSame('/conges/a-valider', $row->filter('a')->attr('href'));
        self::assertNotContains('home-section-watched-projects', $this->sections($crawler));
    }

    public function testTheOwnerOfAnOverrunLeafSeesItAndALeadWatchesItsProject(): void
    {
        $owner = $this->createUser();
        $leaf = $this->overrunLeaf($owner);
        $projectUrl = '/projets/' . $leaf->getProject()->getId();

        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/');
        $mine = $crawler->filter(\sprintf('[data-test="home-my-lot"][data-lot="%d"]', $leaf->getId()));
        self::assertSame($projectUrl, $mine->filter('[data-test="home-my-lot-link"]')->attr('href'));
        self::assertCount(1, $mine->filter('[data-test="signal-overrun"]'));
        self::assertNotContains('home-section-watched-projects', $this->sections($crawler));

        $this->client->loginUser($this->createUser(Role::Lead));
        $crawler = $this->client->request('GET', '/');
        $watched = $crawler->filter(\sprintf('[data-test="home-watched-project"][data-project="%d"]', $leaf->getProject()->getId()));
        self::assertSame($projectUrl, $watched->filter('[data-test="home-watched-project-link"]')->attr('href'));
        self::assertSame('Fin inconnue', $watched->filter('[data-test="home-watched-project-end"]')->text());
        self::assertCount(1, $watched->filter('[data-test="signal-overrun"]'));
        self::assertCount(1, $watched->filter('[data-test="signal-tooltip-overrun"]'));
        self::assertStringNotContainsString((string) $owner->getEmail(), $watched->html(), 'No name of a person.');
        self::assertCount(0, $crawler->filter(\sprintf('[data-test="home-my-lot"][data-lot="%d"]', $leaf->getId())));
    }

    public function testAPersonWithNothingToDoIsToldSoAndOfferedTheirWeek(): void
    {
        $user = $this->createUser();
        $lot = $this->createLot($this->createProject());
        $this->createWeeklyMax($user, '2026-10-05', 1);
        $this->createTimeEntry($user, $lot, '2026-10-05', 1);
        $this->createTimeEntry($user, $lot, '2026-10-12', 1);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/');

        self::assertSame([], $this->sections($crawler));
        self::assertSelectorExists('[data-test="home-empty"]');
        self::assertSame('/saisie', $crawler->filter('[data-test="home-empty-timesheet"]')->attr('href'));
    }

    public function testTheQueryCountDoesNotGrowWithProjectsLeavesAndRequests(): void
    {
        $few = $this->createUser(Role::Lead);
        $many = $this->createUser(Role::Lead);
        foreach ([[$few, 1], [$many, 4]] as [$lead, $count]) {
            $report = $this->createUser()->setManager($lead);
            $this->entityManager()->flush();
            for ($i = 1; $i <= $count; ++$i) {
                $this->overrunLeaf($lead);
                $this->createLot($this->createProject(), owner: $lead);
                $this->createTimeOff($report, \sprintf('2026-12-0%d', $i), \sprintf('2026-12-0%d', $i), TimeOffStatus::Pending);
                $this->createTimeOff($lead, \sprintf('2026-12-1%d', $i), \sprintf('2026-12-1%d', $i), TimeOffStatus::Pending);
            }
        }
        $this->queryCount($few);

        self::assertSame($this->queryCount($few), $this->queryCount($many));
    }

    private function overrunLeaf(User $owner): Lot
    {
        $leaf = $this->planLot($this->createLot($this->createProject(), 1, $owner, title: 'En dépassement'), new \DateTimeImmutable('2026-10-05'), [[$owner, 100]]);
        $this->createTimeEntry($owner, $leaf, '2026-10-05', 4);
        $this->createTimeEntry($owner, $leaf, '2026-10-06', 4);

        return $leaf;
    }

    /**
     * @return list<string>
     */
    private function sections(Crawler $crawler): array
    {
        return $crawler->filter('[data-test^="home-section-"]:not([data-test="home-section-count"])')->each(static fn (Crawler $section): string => (string) $section->attr('data-test'));
    }

    private function queryCount(User $user): int
    {
        $this->client->loginUser($user);
        $this->client->enableProfiler();
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $profile = $this->client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }
}
