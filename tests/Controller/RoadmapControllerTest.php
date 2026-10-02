<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesProgress;
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

final class RoadmapControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesProgress;
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
        self::assertSelectorTextSame('[data-test="roadmap-zoom-level"]', '×1');
        foreach (['roadmap-zoom-out', 'roadmap-zoom-in', 'roadmap-zoom-reset'] as $button) {
            self::assertSelectorExists(\sprintf('button[data-test="%s"][data-action^="roadmap#zoom"]', $button));
        }
        self::assertSelectorExists('[data-controller="roadmap"][data-roadmap-center-value="10.4530"]', 'A zoomed track opens on today, 30 days into the window.');
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
        self::assertSame('1 j', $realized->filter('[data-test="roadmap-run-entered"]')->text());
        self::assertSame('Lun 05/10/2026 → Lun 05/10/2026', $realized->filter('[data-test="roadmap-tooltip-period"]')->text());
        $future = $row->filter('[data-test="roadmap-tooltip-future"]');
        self::assertSame('9 j', $future->filter('[data-test="roadmap-remaining"]')->text());
        self::assertSame('Jeu 08/10/2026 → Mar 20/10/2026', $future->filter('[data-test="roadmap-tooltip-period"]')->text());
        self::assertSame('Test User 100 %', $future->filter('[data-test="roadmap-team"]')->text());
        self::assertSame($future->attr('id'), $row->filter('[data-test="roadmap-bar-future"]')->attr('data-roadmap-tooltip'));
        self::assertCount(0, $row->filter('[data-test="roadmap-leaf-link"]'), 'Prod may not edit a leaf they do not own.');

        $recap = $row->filter('[data-test="roadmap-recap"]');
        self::assertSame($recap->attr('id'), $row->filter('[data-test="roadmap-leaf-title"]')->attr('data-roadmap-tooltip'));
        self::assertSame('0', $row->filter('[data-test="roadmap-leaf-title"]')->attr('tabindex'));
        self::assertSame(
            ['Lun 05/10/2026', 'Mar 20/10/2026', '10 j', '1 j', '9 j', 'Lun 05/10/2026 → Lun 05/10/2026', '1', 'Test User 1 j 100 %'],
            array_map(static fn (string $field): string => $recap->filter(\sprintf('[data-test="%s"]', $field))->text(), ['roadmap-recap-start', 'roadmap-recap-end', 'roadmap-recap-estimate', 'roadmap-recap-entered', 'roadmap-recap-remaining', 'roadmap-recap-period', 'roadmap-days-entered', 'roadmap-recap-team']),
        );
        self::assertCount(0, $crawler->filter('[data-test="roadmap"] [data-tooltip-target]'), 'The tooltips of the roadmap no longer go through Flowbite.');
    }

    public function testInterruptedLeafHasATooltipPerSegmentAndARecapOfTheWholeLeaf(): void
    {
        $client = $this->clientAs('prod@example.com');
        $alice = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 10, $alice, title: 'Interrompue'), new \DateTimeImmutable('2026-09-07'), [[$alice, 100]]);
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'] as $day) {
            $this->createTimeEntry($alice, $leaf, $day, 4);
        }

        $crawler = $client->request('GET', '/roadmap');

        $row = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="roadmap-leaf"][data-title="Interrompue"]', $project->getTitle()));
        $bar = $row->filter('[data-test="roadmap-bar-realized"]');
        self::assertCount(1, $bar);
        self::assertNull($bar->attr('data-roadmap-tooltip'), 'The box of the segments shows no tooltip of its own.');
        $segments = $bar->filter('[data-test="roadmap-segment-realized"]');
        $tooltips = $row->filter('[data-test="roadmap-tooltip-realized"]');
        self::assertSame($tooltips->each(static fn (Crawler $tooltip): ?string => $tooltip->attr('id')), $segments->each(static fn (Crawler $segment): ?string => $segment->attr('data-roadmap-tooltip')));
        self::assertSame(
            [['Lun 07/09/2026 → Ven 11/09/2026', '5 j', '5', 'Test User 5 j 100 %'], ['Lun 21/09/2026 → Ven 25/09/2026', '5 j', '5', 'Test User 5 j 100 %']],
            $tooltips->each(static fn (Crawler $tooltip): array => array_map(static fn (string $field): string => $tooltip->filter(\sprintf('[data-test="%s"]', $field))->text(), ['roadmap-tooltip-period', 'roadmap-run-entered', 'roadmap-days-entered', 'roadmap-team'])),
        );
        $recap = $row->filter('[data-test="roadmap-recap"]');
        self::assertSame('10 j', $recap->filter('[data-test="roadmap-recap-entered"]')->text());
        self::assertSame('Lun 07/09/2026 → Ven 25/09/2026', $recap->filter('[data-test="roadmap-recap-period"]')->text());
        self::assertSame('10', $recap->filter('[data-test="roadmap-days-entered"]')->text());
        self::assertSelectorTextContains('[data-test="roadmap-legend-gap"]', 'Jour ouvré sans saisie');
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
        self::assertSame('en dépassement +50 %', $row->filter('[data-test="signal-overrun"]')->text());
        $recap = $row->filter('[data-test="roadmap-recap"]');
        self::assertSame('1 j', $recap->filter('[data-test="roadmap-overrun"]')->text());
        self::assertSame('+50 %', $recap->filter('[data-test="roadmap-overrun-percent"]')->text());
        self::assertSame('inconnue, estimation à réviser', $recap->filter('[data-test="roadmap-recap-end"]')->text());
        self::assertSame('3 j', $recap->filter('[data-test="roadmap-recap-entered"]')->text());
        $overrun = $row->filter('[data-test="roadmap-tooltip-overrun"]');
        self::assertSame('1 j', $overrun->filter('[data-test="roadmap-run-entered"]')->text());
        self::assertSame('Lun 05/10/2026 → Lun 05/10/2026', $overrun->filter('[data-test="roadmap-tooltip-period"]')->text());
        self::assertSame('1', $overrun->filter('[data-test="roadmap-days-entered"]')->text());
        self::assertSame('2', $row->filter('[data-test="roadmap-tooltip-realized"] [data-test="roadmap-days-entered"]')->text());
        self::assertSame('Jeu 01/10/2026 → Ven 02/10/2026', $row->filter('[data-test="roadmap-tooltip-realized"] [data-test="roadmap-tooltip-period"]')->text(), 'The time within the estimate ends on its last day entered, not on the day before the overrun.');
        self::assertCount(0, $row->filter('[data-test="roadmap-remaining"], [data-test="roadmap-recap-remaining"]'));
        self::assertCount(1, $row->filter('[data-test="roadmap-bar-realized"]'));
        self::assertCount(1, $row->filter('[data-test="roadmap-bar-overrun"]'));
        self::assertCount(0, $row->filter('[data-test="roadmap-bar-future"]'));
    }

    public function testProgressShowsInTheTooltipAndRecapOfALeafAndItsSignalsToEveryone(): void
    {
        $client = $this->clientAs('prod@example.com');
        $alice = $this->createUser();
        $project = $this->createProject();
        $declared = $this->planLot($this->createLot($project, 10, $alice, title: 'Avancée'), new \DateTimeImmutable('2026-10-05'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $declared, '2026-10-05', 4);
        $this->createTimeEntry($alice, $declared, '2026-10-06', 4);
        $this->createProgress($declared, $alice, '2026-10-06', 10, 8);
        $complete = $this->planLot($this->createLot($project, 2, $alice, title: 'Terminée'), new \DateTimeImmutable('2026-09-28'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $complete, '2026-09-28', 4);
        $this->createProgress($complete, $alice, '2026-09-28', 100, 4);
        $toRefresh = $this->planLot($this->createLot($project, 5, $alice, title: 'À actualiser'), new \DateTimeImmutable('2026-09-29'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $toRefresh, '2026-09-29', 4);
        $this->createProgress($toRefresh, $alice, '2026-09-29', 50, 4);
        $this->createTimeEntry($alice, $toRefresh, '2026-09-30', 4);

        $crawler = $client->request('GET', '/roadmap');

        $leaf = static fn (string $title): Crawler => $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="roadmap-leaf"][data-title="%s"]', $project->getTitle(), $title));
        $future = $leaf('Avancée')->filter('[data-test="roadmap-tooltip-future"]');
        self::assertSame(
            ['18 j', '10 % au 06/10', '20 j pour 10 j estimés (+10 j)'],
            array_map(static fn (string $field): string => $future->filter(\sprintf('[data-test="%s"]', $field))->text(), ['roadmap-remaining', 'roadmap-progress', 'roadmap-projected']),
        );
        $recap = $leaf('Avancée')->filter('[data-test="roadmap-recap"]');
        self::assertSame(['Lun 02/11/2026', '18 j', '10 % au 06/10'], [$recap->filter('[data-test="roadmap-recap-end"]')->text(), $recap->filter('[data-test="roadmap-recap-remaining"]')->text(), $recap->filter('[data-test="roadmap-progress"]')->text()]);

        self::assertSame('terminée à 100 %', $leaf('Terminée')->filter('[data-test="signal-completed"]')->text());
        self::assertSame('Lun 28/09/2026', $leaf('Terminée')->filter('[data-test="roadmap-recap-end"]')->text());
        self::assertSame('avancement à actualiser', $leaf('À actualiser')->filter('[data-test="signal-progress_to_refresh"]')->text());
        self::assertSame('inconnue, avancement à actualiser', $leaf('À actualiser')->filter('[data-test="roadmap-recap-end"]')->text());
    }

    public function testTooltipOfASegmentSaysWhatEachPersonEnteredOnIt(): void
    {
        $client = $this->clientAs('prod@example.com');
        $alice = $this->createUser();
        $outsider = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 2, $alice, title: 'Renforcée'), new \DateTimeImmutable('2026-10-01'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $leaf, '2026-10-01', 4);
        $this->createTimeEntry($outsider, $leaf, '2026-10-02', 4);
        $this->createTimeEntry($alice, $leaf, '2026-10-05', 2);

        $crawler = $client->request('GET', '/roadmap');

        $row = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="roadmap-leaf"][data-title="Renforcée"]', $project->getTitle()));
        $lines = static fn (string $kind): array => $row->filter(\sprintf('[data-test="roadmap-tooltip-%s"] [data-test="roadmap-team-line"]', $kind))->each(static fn (Crawler $line): string => $line->text());
        self::assertSame(['Test User 1 j 100 %', 'Test User 1 j hors équipe'], $lines('realized'));
        self::assertSame(['Test User 0,5 j 100 %'], $lines('overrun'));
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
        $recap = static fn (string $title, string $field): string => $projectRow->filter(\sprintf('[data-title="%s"] [data-test="roadmap-recap"] [data-test="%s"]', $title, $field))->text();
        self::assertSame('à estimer', $recap('À estimer', 'roadmap-recap-estimate'));
        self::assertSame('sans début', $recap('Sans début', 'roadmap-recap-start'));
        self::assertSame('non calculée', $recap('Sans début', 'roadmap-recap-end'));
        self::assertSame('aucune saisie', $recap('Sans début', 'roadmap-recap-period'));
        self::assertSame('sans équipe', $recap('Sans équipe', 'roadmap-recap-team'));
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
