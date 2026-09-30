<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class RoadmapControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    protected function setUp(): void
    {
        self::mockTime('2026-10-07 10:00');
    }

    #[DataProvider('roleProvider')]
    public function testEveryoneReadsTheRoadmap(string $email): void
    {
        $client = $this->clientAs($email);

        $client->request('GET', '/roadmap');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test="roadmap-period"]', 'Du 07/09/2026 au 20/06/2027');
        self::assertSelectorExists('[data-test="roadmap-today"][aria-current="page"]');
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function roleProvider(): \Generator
    {
        yield 'direction' => ['admin@example.com'];
        yield 'lead' => ['lead@example.com'];
        yield 'prod' => ['prod@example.com'];
    }

    public function testPlannedLeafShowsItsBarsWithTheirDetailsInTooltips(): void
    {
        $client = $this->clientAs('prod@example.com');
        $alice = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 10, $alice, title: 'Socle'), new \DateTimeImmutable('2026-10-05'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $leaf, '2026-10-05', 4);

        $crawler = $client->request('GET', '/roadmap');

        $row = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="roadmap-leaf"][data-title="Socle"]', $project->getTitle()));
        self::assertCount(1, $row);
        self::assertCount(1, $row->filter('[data-test="roadmap-bar-realized"]'));
        self::assertCount(1, $row->filter('[data-test="roadmap-bar-future"]'));
        $realized = $row->filter('[data-test="roadmap-tooltip-realized"]');
        self::assertSame('1 j', $realized->filter('[data-test="roadmap-realized"]')->text());
        self::assertSame('Lun 05/10/2026 → Lun 05/10/2026', $realized->filter('[data-test="roadmap-tooltip-period"]')->text());
        $future = $row->filter('[data-test="roadmap-tooltip-future"]');
        self::assertSame('9 j', $future->filter('[data-test="roadmap-remaining"]')->text());
        self::assertSame('Jeu 08/10/2026 → Mar 20/10/2026', $future->filter('[data-test="roadmap-tooltip-period"]')->text());
        self::assertSame('Test User 100 %', $future->filter('[data-test="roadmap-team"]')->text());
        self::assertCount(0, $row->filter('[data-test="roadmap-leaf-link"]'), 'Prod may not edit a leaf they do not own.');
    }

    public function testOverrunLeafShowsByHowMuchAndThatItsEndIsUnknown(): void
    {
        $client = $this->clientAs('prod@example.com');
        $alice = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 2, $alice, title: 'Dépassée'), new \DateTimeImmutable('2026-10-01'), [[$alice, 100]]);
        foreach (['2026-10-01', '2026-10-02', '2026-10-05'] as $day) {
            $this->createTimeEntry($alice, $leaf, $day, 4);
        }

        $crawler = $client->request('GET', '/roadmap');

        $row = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="roadmap-leaf"][data-title="Dépassée"]', $project->getTitle()));
        self::assertCount(1, $row->filter('[data-test="signal-overrun"]'));
        $overrun = $row->filter('[data-test="roadmap-tooltip-overrun"]');
        self::assertSame('1 j', $overrun->filter('[data-test="roadmap-overrun"]')->text());
        self::assertSame('Lun 05/10/2026 → Lun 05/10/2026', $overrun->filter('[data-test="roadmap-tooltip-period"]')->text());
        self::assertCount(0, $row->filter('[data-test="roadmap-remaining"]'));
        self::assertCount(1, $row->filter('[data-test="roadmap-bar-realized"]'));
        self::assertCount(1, $row->filter('[data-test="roadmap-bar-overrun"]'));
        self::assertCount(0, $row->filter('[data-test="roadmap-bar-future"]'));
    }

    public function testLeavesWithoutABarSayWhatTheyMissAndTheirProjectIsPartial(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $this->createLot($project, null, title: 'À estimer');
        $this->createLot($project, 5, $this->createUser(), title: 'Sans début');
        $this->planLot($this->createLot($project, 5, title: 'Sans équipe'), new \DateTimeImmutable('2026-10-12'), []);

        $crawler = $client->request('GET', '/roadmap');

        $projectRow = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"]', $project->getTitle()));
        self::assertCount(1, $projectRow->filter('summary [data-test="signal-partial_planning"]'));
        self::assertCount(1, $projectRow->filter('[data-title="À estimer"] [data-test="signal-to_estimate"]'));
        self::assertCount(1, $projectRow->filter('[data-title="Sans début"] [data-test="signal-without_start"]'));
        self::assertCount(1, $projectRow->filter('[data-title="Sans équipe"] [data-test="signal-without_team"]'));
        self::assertCount(0, $projectRow->filter('[data-test^="roadmap-bar-"]'));
        self::assertCount(1, $crawler->filter('[data-test="roadmap-project"][data-title="Évolution du portail"] [data-test="signal-unsplit"]'));
    }

    public function testOverloadIsShownToLeadsAndAbsentFromThePageOfProd(): void
    {
        $alice = $this->createUser();
        $project = $this->createProject();
        $this->planLot($this->createLot($project, 10, $alice, title: 'Première'), new \DateTimeImmutable('2026-10-12'), [[$alice, 100]]);
        $this->planLot($this->createLot($project, 10, $alice, title: 'Seconde'), new \DateTimeImmutable('2026-10-19'), [[$alice, 50]]);
        $selector = \sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="signal-to_replan"]', $project->getTitle());

        $crawler = $this->clientAs('lead@example.com')->request('GET', '/roadmap');
        self::assertCount(2, $crawler->filter($selector));

        $crawler = $this->clientAs('prod@example.com')->request('GET', '/roadmap');
        self::assertCount(0, $crawler->filter($selector));
        self::assertStringNotContainsString('à replanifier', (string) $crawler->filter('[data-test="roadmap"]')->html());
    }

    public function testLeadOpensALeafFromTheRoadmapAndComesBackToTheSameWindow(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $leaf = $this->createLot($project, 5, title: 'Socle');

        $crawler = $client->request('GET', '/roadmap/2026-W50');
        $link = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="roadmap-leaf-link"]', $project->getTitle()));
        self::assertSame('/lots/' . $leaf->getId() . '/modifier?roadmap=2026-W50', $link->attr('href'));

        $crawler = $client->request('GET', (string) $link->attr('href'));
        self::assertSame('/roadmap/2026-W50', $crawler->filter('[data-test="lot-form"] a')->attr('href'));
        $client->submit($crawler->filter('[data-test="lot-form"]')->form(['lot[estimateDays]' => '6']));
        self::assertResponseRedirects('/roadmap/2026-W50');

        $client->request('GET', '/lots/' . $leaf->getId() . '/modifier?roadmap=https://example.com');
        self::assertSame('/projets/' . $project->getId(), $client->getCrawler()->filter('[data-test="lot-form"] a')->attr('href'));
    }

    public function testNavigatesByFourWeeksAndRefusesAWeekThatDoesNotExist(): void
    {
        $client = $this->clientAs('lead@example.com');

        $crawler = $client->request('GET', '/roadmap/2026-W50');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test="roadmap-period"]', 'Du 09/11/2026 au 22/08/2027');
        self::assertSame('/roadmap/2026-W46', $crawler->filter('[data-test="roadmap-previous"]')->attr('href'));
        self::assertSame('/roadmap/2027-W01', $crawler->filter('[data-test="roadmap-next"]')->attr('href'));
        self::assertSelectorNotExists('[data-test="roadmap-today"][aria-current="page"]');

        $client->request('GET', '/roadmap/2026-W60');
        self::assertResponseStatusCodeSame(404);
    }

    public function testQueryCountDoesNotGrowWithTheNumberOfLeaves(): void
    {
        $client = $this->clientAs('lead@example.com');
        $small = $this->queryCount($client);

        $alice = $this->createUser();
        $project = $this->createProject();
        foreach (['2026-10-05', '2026-10-19', '2026-11-02'] as $start) {
            $this->planLot($this->createLot($project, 10, $alice), new \DateTimeImmutable($start), [[$alice, 100], [$this->createUser(), 50]]);
        }

        self::assertSame($small, $this->queryCount($client));
    }

    private function queryCount(KernelBrowser $client): int
    {
        $client->enableProfiler();
        $client->request('GET', '/roadmap');
        self::assertResponseIsSuccessful();

        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    private function clientAs(string $email): KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $userRepository = self::getContainer()->get(UserRepository::class);
        \assert($userRepository instanceof UserRepository);
        $user = $userRepository->findOneByEmail($email);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user);

        return $client;
    }
}
