<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Project;
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
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class RoadmapProjectControllerTest extends WebTestCase
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
    public function testEveryoneReadsThePageOfAProjectWithoutAnyWayToManageIt(string $email): void
    {
        $client = $this->clientAs($email);
        $project = $this->createExample();

        $crawler = $client->request('GET', $this->url($project));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-test="project-page-heading"]', (string) $project->getTitle());
        self::assertSame(['nav-roadmap'], $crawler->filter('#app-sidebar [aria-current="page"]')->each(static fn (Crawler $link): string => (string) $link->attr('data-test')));
        self::assertCount(0, $crawler->filter('[data-test="roadmap-leaf-link"]'), 'The title of a leaf never links to its edition here.');
        self::assertCount(2, $crawler->filter('[data-test="roadmap-leaf-title"]'));
        self::assertCount(0, $crawler->filter('main a[href*="/modifier"], main form'));
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

    public function testSummaryAndConsumptionTellWhereTheProjectStands(): void
    {
        $client = $this->clientAs('prod@example.com');
        $project = $this->createExample();

        $crawler = $client->request('GET', $this->url($project));

        self::assertSame(
            ['18 j', '13 j', '5 j', '0 j', 'Lun 07/09/2026', 'Mer 14/10/2026'],
            array_map(static fn (string $field): string => $crawler->filter(\sprintf('[data-test="project-page-%s"]', $field))->text(), ['estimate', 'entered', 'remaining', 'overrun', 'start', 'end']),
        );
        self::assertSame([
            'API' => ['10 j', '10 j', '0 j'],
            'Front' => ['8 j', '3 j', '5 j'],
            'Login' => ['8 j', '3 j', '5 j'],
            'Projet' => ['18 j', '13 j', '5 j'],
        ], self::consumption($crawler));
    }

    public function testCumulKeepsWhatRemainsApartFromWhatGoesBeyond(): void
    {
        $client = $this->clientAs('prod@example.com');
        $alice = $this->createUser();
        $project = $this->createProject();
        $split = $this->createLot($project, title: 'Back-office');
        $beyond = $this->createLot($project, 5, $alice, $split, 'Dépassé');
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18'] as $day) {
            $this->createTimeEntry($alice, $beyond, $day, 4);
        }
        $started = $this->createLot($project, 5, $alice, $split, 'Entamé');
        $this->createTimeEntry($alice, $started, '2026-09-21', 4);
        $this->createTimeEntry($alice, $started, '2026-09-22', 4);

        $crawler = $client->request('GET', $this->url($project));

        $row = $crawler->filter('[data-test="consumption-row"][data-title="Back-office"]');
        self::assertSame('3 j', $row->filter('[data-test="consumption-remaining"]')->text());
        self::assertSame('5 j de dépassement', $row->filter('[data-test="consumption-overrun"]')->text());
        self::assertSame('5 j de dépassement', $crawler->filter('[data-test="consumption-row"][data-title="Dépassé"] [data-test="consumption-overrun"]')->text());
        self::assertCount(0, $crawler->filter('[data-test="consumption-row"][data-title="Dépassé"] [data-test="consumption-remaining"]'));
        self::assertSame(['3 j', '5 j'], [$crawler->filter('[data-test="project-page-remaining"]')->text(), $crawler->filter('[data-test="project-page-overrun"]')->text()]);
    }

    public function testLeafToEstimateIsPartOfTheTableAndOfTheTimelineWithoutABarOnTheFrieze(): void
    {
        $client = $this->clientAs('prod@example.com');
        $alice = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->createLot($project, null, $alice, title: 'Cadrage');
        $this->createTimeEntry($alice, $leaf, '2026-09-30', 4);

        $crawler = $client->request('GET', $this->url($project));

        $row = $crawler->filter('[data-test="consumption-row"][data-title="Cadrage"]');
        self::assertCount(1, $row->filter('[data-test="leaf-to-estimate"]'));
        self::assertSame('1 j', $row->filter('[data-test="consumption-entered"]')->text());
        self::assertCount(0, $row->filter('[data-test="consumption-remaining"]'));
        self::assertCount(1, $crawler->filter('[data-test="project-page-estimate"] [data-test="estimate-partial"]'));
        self::assertCount(0, $crawler->filter('[data-test="roadmap-leaf"][data-title="Cadrage"] [data-test^="roadmap-bar"]'));
        self::assertSame(['Cadrage'], $crawler->filter('[data-test="timeline-entry-leaf"]')->each(static fn (Crawler $leafPath): string => $leafPath->text()));
    }

    public function testFriezeSpansTheProjectAndOpensUnzoomedWithoutRememberingItsZoom(): void
    {
        $client = $this->clientAs('prod@example.com');
        $project = $this->createExample();

        $client->request('GET', $this->url($project));

        self::assertSelectorTextContains('[data-test="roadmap-period"]', 'Du 07/09/2026 au 18/10/2026');
        self::assertSelectorExists('[data-controller="roadmap"][data-roadmap-remember-value="false"][data-roadmap-center-value="71.4286"]', 'The frieze opens on today, 30 days into its 42.');
        self::assertSelectorTextSame('[data-test="roadmap-zoom-level"]', '×1');
        self::assertSelectorExists('[data-test="roadmap"][style*="--roadmap-weeks: 6;"]');
        self::assertSelectorExists('[data-test="roadmap"][style*="--roadmap-min-track: 52.0000rem"]', 'Shorter than the roadmap, the frieze fills the width.');
        self::assertSelectorExists('[data-test="roadmap-today-line"]');
    }

    public function testFriezeOfAProjectLongerThanTheRoadmapWidensToLabelEveryMonth(): void
    {
        $client = $this->clientAs('prod@example.com');
        $alice = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->createLot($project, 300, $alice, title: 'Socle');
        $this->createTimeEntry($alice, $leaf, '2025-09-01', 4);
        $this->createTimeEntry($alice, $leaf, '2026-09-28', 4);

        $crawler = $client->request('GET', $this->url($project));

        self::assertSelectorExists('[data-test="roadmap"][style*="--roadmap-weeks: 57;"]');
        self::assertSelectorExists('[data-test="roadmap"][style*="--roadmap-min-track: 72.2927rem"]', '57 weeks as wide each as the 41 of the roadmap on its 52rem.');
        self::assertCount(14, $crawler->filter('[data-test="roadmap-months"] span'), 'From September 2025 to October 2026, every month has its label.');
    }

    public function testTimelineListsEveryRunFromTheLatestToTheEarliest(): void
    {
        $client = $this->clientAs('prod@example.com');
        $project = $this->createExample();

        $crawler = $client->request('GET', $this->url($project));

        self::assertSame(['Septembre 2026'], $crawler->filter('[data-test="timeline-month-label"]')->each(static fn (Crawler $label): string => $label->text()));
        $entries = $crawler->filter('[data-test="timeline-entry"]');
        self::assertSame([
            ['API', 'Lun 21/09/2026 → Ven 25/09/2026', '5 j', '5', 'Test User 5 j 100 %'],
            ['Front · Login', 'Lun 14/09/2026 → Mer 16/09/2026', '3 j', '3', 'Test User 3 j 100 %'],
            ['API', 'Lun 07/09/2026 → Ven 11/09/2026', '5 j', '5', 'Test User 5 j 100 %'],
        ], $entries->each(static fn (Crawler $entry): array => array_map(static fn (string $field): string => $entry->filter(\sprintf('[data-test="%s"]', $field))->text(), ['timeline-entry-leaf', 'timeline-entry-period', 'timeline-entry-entered', 'timeline-entry-days', 'roadmap-team'])));
        self::assertCount(0, $crawler->filter('[data-test="timeline-entry-overrun"]'));
    }

    public function testRunBeyondTheEstimateIsMarkedOnTheTimeline(): void
    {
        $client = $this->clientAs('prod@example.com');
        $alice = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 1, $alice, title: 'Socle'), new \DateTimeImmutable('2026-09-28'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $leaf, '2026-09-28', 4);
        $this->createTimeEntry($alice, $leaf, '2026-09-29', 2);

        $crawler = $client->request('GET', $this->url($project));

        $entries = $crawler->filter('[data-test="timeline-entry"]');
        self::assertCount(2, $entries);
        self::assertCount(1, $entries->eq(0)->filter('[data-test="timeline-entry-overrun"]'));
        self::assertCount(0, $entries->eq(1)->filter('[data-test="timeline-entry-overrun"]'));
    }

    public function testComesBackToTheWeekOfTheRoadmapItWasOpenedFrom(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $this->createLot($project, 5, title: 'Socle');

        $crawler = $client->request('GET', '/roadmap/2026-W50');
        $link = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="roadmap-project-open"]', $project->getTitle()));
        self::assertSame($this->url($project) . '?roadmap=2026-W50', $link->attr('href'));
        self::assertSame('Ouvrir la fiche de ' . $project->getTitle(), $link->attr('aria-label'));

        $crawler = $client->request('GET', (string) $link->attr('href'));
        self::assertSame('/roadmap/2026-W50', $crawler->filter('[data-test="project-page-back"]')->attr('href'));

        $crawler = $client->request('GET', $this->url($project) . '?roadmap=2026-W60');
        self::assertResponseIsSuccessful();
        self::assertSame('/roadmap', $crawler->filter('[data-test="project-page-back"]')->attr('href'));
    }

    public function testProjectWithoutDateHasNoFriezeNorTimeline(): void
    {
        $client = $this->clientAs('prod@example.com');
        $project = $this->createProject();
        $this->createLot($project, 5, $this->createUser(), title: 'Socle');

        $client->request('GET', $this->url($project));

        self::assertSelectorExists('[data-test="consumption"]');
        self::assertSelectorExists('[data-test="project-page-no-frieze"]');
        self::assertSelectorNotExists('[data-test="roadmap"]');
        self::assertSelectorNotExists('[data-controller="roadmap"]');
        self::assertSelectorExists('[data-test="timeline-empty"]');
    }

    public function testUnsplitProjectSaysSoOnly(): void
    {
        $client = $this->clientAs('prod@example.com');
        $project = $this->createProject();

        $client->request('GET', $this->url($project));

        self::assertSelectorTextContains('[data-test="project-page-unsplit"]', 'pas encore découpé');
        self::assertSelectorNotExists('[data-test="consumption"]');
        self::assertSelectorNotExists('[data-test="roadmap"]');
        self::assertSelectorNotExists('[data-test="timeline"]');
        self::assertSelectorNotExists('[data-test="timeline-empty"]');
    }

    public function testUnknownProjectIsNotFound(): void
    {
        $this->clientAs('prod@example.com')->request('GET', '/roadmap/projets/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testQueryCountDoesNotGrowWithTheNumberOfLeaves(): void
    {
        $client = $this->clientAs('lead@example.com');
        $alice = $this->createUser();
        $small = $this->createProject();
        $leaf = $this->planLot($this->createLot($small, 10, $alice), new \DateTimeImmutable('2026-09-28'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $leaf, '2026-09-28', 4);
        $large = $this->createProject();
        foreach (['2026-09-28', '2026-10-05', '2026-10-19', '2026-11-02'] as $start) {
            $leaf = $this->planLot($this->createLot($large, 10, $alice), new \DateTimeImmutable($start), [[$alice, 50], [$this->createUser(), 50]]);
            $this->createTimeEntry($alice, $leaf, '2026-09-29', 2);
        }
        $this->queryCount($client, $small);

        self::assertSame($this->queryCount($client, $small), $this->queryCount($client, $large));
    }

    /**
     * The example of the pitch: « API » held by one person and entered two weeks apart, « Front · Login » held by
     * another and entered three days.
     */
    private function createExample(): Project
    {
        $alice = $this->createUser();
        $bob = $this->createUser();
        $project = $this->createProject();
        $api = $this->planLot($this->createLot($project, 10, $alice, title: 'API'), new \DateTimeImmutable('2026-09-07'), [[$alice, 100]]);
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'] as $day) {
            $this->createTimeEntry($alice, $api, $day, 4);
        }
        $front = $this->createLot($project, title: 'Front');
        $login = $this->planLot($this->createLot($project, 8, $bob, $front, 'Login'), new \DateTimeImmutable('2026-09-14'), [[$bob, 100]]);
        foreach (['2026-09-14', '2026-09-15', '2026-09-16'] as $day) {
            $this->createTimeEntry($bob, $login, $day, 4);
        }

        return $project;
    }

    /**
     * @return array<string, list<string>> estimate, time entered and what remains, by title
     */
    private static function consumption(Crawler $crawler): array
    {
        $lines = [];
        $crawler->filter('[data-test="consumption-row"]')->each(static function (Crawler $row) use (&$lines): void {
            $lines[(string) $row->attr('data-title')] = array_map(static fn (string $field): string => trim($row->filter(\sprintf('[data-test="%s"]', $field))->text()), ['consumption-estimate', 'consumption-entered', 'consumption-remaining']);
        });

        return $lines;
    }

    private function url(Project $project): string
    {
        return '/roadmap/projets/' . $project->getId();
    }

    private function queryCount(KernelBrowser $client, Project $project): int
    {
        $client->enableProfiler();
        $client->request('GET', $this->url($project));
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
