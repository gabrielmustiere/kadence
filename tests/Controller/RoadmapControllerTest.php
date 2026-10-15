<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\Type\RoadmapSignal;
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
        yield 'direction' => ['thomas.cerqueus@anyti.me'];
        yield 'lead' => ['olivier.thomas@anyti.me'];
        yield 'prod' => ['antoine.leroy@anyti.me'];
    }

    public function testPlannedLeafShowsItsBarsWithTheirDetailsInTooltips(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
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
        $client = $this->clientAs('antoine.leroy@anyti.me');
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
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $alice = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->planLot($this->createLot($project, 2, $alice, title: 'Dépassée'), new \DateTimeImmutable('2026-10-01'), [[$alice, 100]]);
        foreach (['2026-10-01', '2026-10-02', '2026-10-05'] as $day) {
            $this->createTimeEntry($alice, $leaf, $day, 4);
        }

        $crawler = $client->request('GET', '/roadmap');

        $row = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="roadmap-leaf"][data-title="Dépassée"]', $project->getTitle()));
        self::assertSame('en dépassement de 1 j (50 %)', $row->filter('[data-test="signal-overrun"]')->text());
        $recap = $row->filter('[data-test="roadmap-recap"]');
        self::assertSame('1 j (50 %)', $recap->filter('[data-test="roadmap-overrun"]')->text());
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
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $alice = $this->createUser();
        $project = $this->createProject();
        $declared = $this->planLot($this->createLot($project, 10, $alice, title: 'Avancée'), new \DateTimeImmutable('2026-10-05'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $declared, '2026-10-05', 4);
        $this->createTimeEntry($alice, $declared, '2026-10-06', 4);
        $this->createProgress($declared, $alice, '2026-10-06', 10);
        $complete = $this->planLot($this->createLot($project, 2, $alice, title: 'Terminée'), new \DateTimeImmutable('2026-09-28'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $complete, '2026-09-28', 4);
        $this->createProgress($complete, $alice, '2026-09-28', 100);
        $toRefresh = $this->planLot($this->createLot($project, 5, $alice, title: 'À actualiser'), new \DateTimeImmutable('2026-09-29'), [[$alice, 100]]);
        $this->createTimeEntry($alice, $toRefresh, '2026-09-29', 4);
        $this->createProgress($toRefresh, $alice, '2026-09-29', 50);
        $this->createTimeEntry($alice, $toRefresh, '2026-09-30', 4);

        $crawler = $client->request('GET', '/roadmap');

        $leaf = static fn (string $title): Crawler => $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="roadmap-leaf"][data-title="%s"]', $project->getTitle(), $title));
        $future = $leaf('Avancée')->filter('[data-test="roadmap-tooltip-future"]');
        self::assertSame(
            ['18 j', '10 % au 06/10', '20 j pour une estimation de 10 j, +10 j (+100 %)'],
            array_map(static fn (string $field): string => $future->filter(\sprintf('[data-test="%s"]', $field))->text(), ['roadmap-remaining', 'roadmap-progress', 'roadmap-projected']),
        );
        $recap = $leaf('Avancée')->filter('[data-test="roadmap-recap"]');
        self::assertSame(['Lun 02/11/2026', '18 j', '10 % au 06/10'], [$recap->filter('[data-test="roadmap-recap-end"]')->text(), $recap->filter('[data-test="roadmap-recap-remaining"]')->text(), $recap->filter('[data-test="roadmap-progress"]')->text()]);

        self::assertSame('terminé à 100 %', $leaf('Terminée')->filter('[data-test="signal-completed"]')->text());
        self::assertSame('Lun 28/09/2026', $leaf('Terminée')->filter('[data-test="roadmap-recap-end"]')->text());
        self::assertSame('avancement à actualiser', $leaf('À actualiser')->filter('[data-test="signal-progress_to_refresh"]')->text());
        self::assertSame('Ven 09/10/2026', $leaf('À actualiser')->filter('[data-test="roadmap-recap-end"]')->text(), 'A progress to refresh keeps its end.');
    }

    public function testTooltipOfASegmentSaysWhatEachPersonEnteredOnIt(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
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
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $project = $this->createProject();
        $this->createLot($project, null, title: 'À estimer');
        $this->createLot($project, 5, $this->createUser(), title: 'Sans début');
        $this->planLot($this->createLot($project, 5, title: 'Sans équipe'), new \DateTimeImmutable('2026-10-12'), []);
        $unsplit = $this->createProject();

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
        self::assertCount(1, $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="signal-unsplit"]', $unsplit->getTitle())));
    }

    public function testPlannedProjectWritesTheEndOfItsLatestLeafNextToItsTitle(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $alice = $this->createUser();
        $project = $this->createProject();
        $bob = $this->createUser();
        $this->planLot($this->createLot($project, 10, $alice, title: 'Socle'), new \DateTimeImmutable('2026-10-12'), [[$alice, 100]]);
        $this->planLot($this->createLot($project, 2, $bob, title: 'Recette'), new \DateTimeImmutable('2026-10-12'), [[$bob, 100]]);

        $crawler = $client->request('GET', '/roadmap');

        $projectRow = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"]', $project->getTitle()));
        $end = $projectRow->filter('summary [data-test="roadmap-end"]');
        self::assertSame('Fin calculée : Ven 23/10/2026', $end->text());
        self::assertSame('known', $end->attr('data-state'));
        self::assertSame('2026-10-23', $end->filter('time')->attr('datetime'));
        self::assertNull($projectRow->filter('summary')->attr('aria-describedby'));
        self::assertCount(0, $projectRow->filter('[data-test="roadmap-end-tooltip"]'));
        $leaf = $projectRow->filter('[data-test="roadmap-leaf"][data-title="Socle"]');
        self::assertSame('Fin calculée : ' . $leaf->filter('[data-test="roadmap-recap-end"]')->text(), $leaf->filter('[data-test="roadmap-end"]')->text());
        self::assertSame('Fin calculée : Mar 13/10/2026', $projectRow->filter('[data-test="roadmap-leaf"][data-title="Recette"] [data-test="roadmap-end"]')->text());
    }

    #[DataProvider('roleProvider')]
    public function testUnknownEndCountsTheLeavesInCauseAndListsThemInATooltip(string $email): void
    {
        $client = $this->clientAs($email);
        $alice = $this->createUser();
        $project = $this->createProject();
        $front = $this->createLot($project, title: 'Front');
        $leaf = $this->planLot($this->createLot($project, 2, $alice, $front, 'Dépassée'), new \DateTimeImmutable('2026-10-01'), [[$alice, 100]]);
        foreach (['2026-10-01', '2026-10-02', '2026-10-05'] as $day) {
            $this->createTimeEntry($alice, $leaf, $day, 4);
        }
        $this->planLot($this->createLot($project, 5, $alice, $front, 'Écrans'), new \DateTimeImmutable('2026-10-12'), [[$alice, 100]]);

        $crawler = $client->request('GET', '/roadmap');

        $projectRow = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"]', $project->getTitle()));
        $summary = $projectRow->filter('summary');
        $end = $summary->filter('[data-test="roadmap-end"]');
        self::assertSame('Fin inconnue : 1 lot en cause', $end->text());
        self::assertSame('unknown', $end->attr('data-state'));
        self::assertSame('div', $end->nodeName(), 'A summary may not hold a button.');
        $tooltip = $summary->filter('[data-test="roadmap-end-tooltip"]');
        self::assertSame('roadmap-end-project-' . $project->getId(), $tooltip->attr('id'));
        self::assertSame($tooltip->attr('id'), $end->attr('data-roadmap-tooltip'));
        self::assertSame($tooltip->attr('id'), $summary->attr('aria-describedby'));
        self::assertSame(['Front · Dépassée : en dépassement'], $tooltip->filter('[data-test="unknown-end"]')->each(static fn (Crawler $item): string => $item->text()));

        $lot = $projectRow->filter('[data-test="roadmap-lot"][data-title="Front"]');
        $lotEnd = $lot->filter('[data-test="roadmap-end"]');
        self::assertSame('Fin inconnue : 1 lot en cause', $lotEnd->text());
        self::assertSame('button', $lotEnd->nodeName());
        self::assertSame('roadmap-end-lot-' . $front->getId(), $lotEnd->attr('aria-describedby'));
        self::assertSame($lotEnd->attr('aria-describedby'), $lot->filter('[data-test="roadmap-end-tooltip"]')->attr('id'));
        self::assertSame('Fin inconnue : en dépassement', $projectRow->filter('[data-test="roadmap-leaf"][data-title="Dépassée"] [data-test="roadmap-end"]')->text());
        self::assertSame('known', $projectRow->filter('[data-test="roadmap-leaf"][data-title="Écrans"] [data-test="roadmap-end"]')->attr('data-state'));
    }

    public function testPartialProjectLeavesOutItsUnplannedLeavesAndSaysWhatTheyMiss(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $alice = $this->createUser();
        $project = $this->createProject();
        $this->planLot($this->createLot($project, 10, $alice, title: 'Socle'), new \DateTimeImmutable('2026-10-12'), [[$alice, 100]]);
        $this->createLot($project, null, title: 'À estimer');
        $this->createLot($project, 5, $alice, title: 'Sans début');

        $crawler = $client->request('GET', '/roadmap');

        $projectRow = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"]', $project->getTitle()));
        self::assertSame('Fin calculée : Ven 23/10/2026, hors 2 lots non planifiés', $projectRow->filter('summary [data-test="roadmap-end"]')->text());
        $tooltip = $projectRow->filter('summary [data-test="roadmap-end-tooltip"]');
        self::assertStringStartsWith('Laissés hors de sa fin calculée', $tooltip->filter('[data-test="unknown-ends"] p')->text());
        self::assertSame(['À estimer : à estimer, sans début, sans équipe', 'Sans début : sans début'], $tooltip->filter('[data-test="unknown-end"]')->each(static fn (Crawler $item): string => $item->text()));
        $leafEnd = $projectRow->filter('[data-test="roadmap-leaf"][data-title="À estimer"] [data-test="roadmap-end"]');
        self::assertSame('Non planifié : à estimer, sans début, sans équipe', $leafEnd->text());
        self::assertSame('unplanned', $leafEnd->attr('data-state'));
    }

    public function testProjectWithNothingPlannedOrNoLotSaysSo(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $project = $this->createProject();
        $this->createLot($project, null, title: 'À estimer');
        $this->createLot($project, 5, $this->createUser(), title: 'Sans début');
        $this->planLot($this->createLot($project, 5, title: 'Sans équipe'), new \DateTimeImmutable('2026-10-12'), []);
        $unsplit = $this->createProject();

        $crawler = $client->request('GET', '/roadmap');

        $end = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] summary [data-test="roadmap-end"]', $project->getTitle()));
        self::assertSame('Non planifié : 3 lots', $end->text());
        self::assertSame('unplanned', $end->attr('data-state'));
        self::assertCount(3, $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] summary [data-test="unknown-end"]', $project->getTitle())));
        $unsplitEnd = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] summary [data-test="roadmap-end"]', $unsplit->getTitle()));
        self::assertSame('Pas encore découpé en lots', $unsplitEnd->text());
        self::assertSame('unsplit', $unsplitEnd->attr('data-state'));
    }

    public function testNoEndExplanationEverSaysToReplan(): void
    {
        $alice = $this->createUser();
        $project = $this->createProject();
        $this->planLot($this->createLot($project, 10, $alice, title: 'Première'), new \DateTimeImmutable('2026-10-12'), [[$alice, 100]]);
        $this->planLot($this->createLot($project, 10, $alice, title: 'Seconde'), new \DateTimeImmutable('2026-10-19'), [[$alice, 50]]);
        $this->createLot($project, null, title: 'À estimer');

        $crawler = $this->clientAs('olivier.thomas@anyti.me')->request('GET', '/roadmap');

        $projectRow = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"]', $project->getTitle()));
        self::assertCount(2, $projectRow->filter('[data-test="signal-to_replan"]'));
        foreach ($projectRow->filter('[data-test="roadmap-end"], [data-test="roadmap-end-tooltip"]') as $node) {
            self::assertStringNotContainsString('replanifier', (string) $node->textContent);
        }
    }

    public function testEndOutsideTheWindowIsStillWrittenAndMarkedAtTheEdgeOfTheFrieze(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $alice = $this->createUser();
        $past = $this->createProject();
        $this->planLot($this->createLot($past, 10, $alice, title: 'Socle'), new \DateTimeImmutable('2026-10-12'), [[$alice, 100]]);
        $future = $this->createProject();
        $this->planLot($this->createLot($future, 5, $alice, title: 'Suite'), new \DateTimeImmutable('2027-09-06'), [[$alice, 100]]);
        $unsplit = $this->createProject();
        $row = static fn (Crawler $crawler, string $title): Crawler => $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"]', $title));

        $crawler = $client->request('GET', '/roadmap/2027-W30');
        $projectRow = $row($crawler, (string) $past->getTitle());
        self::assertSame('Fin calculée : Ven 23/10/2026', $projectRow->filter('summary [data-test="roadmap-end"]')->text());
        self::assertSame('before', $projectRow->filter('summary [data-test="roadmap-outside"]')->attr('data-side'));
        self::assertSame('before', $projectRow->filter('[data-test="roadmap-leaf"] [data-test="roadmap-outside"]')->attr('data-side'));
        self::assertCount(0, $projectRow->filter('[data-test^="roadmap-bar-"]'));
        self::assertCount(0, $row($crawler, (string) $future->getTitle())->filter('[data-test="roadmap-outside"]'), 'A bar within the window has no mark.');

        $crawler = $client->request('GET', '/roadmap');
        self::assertSame('after', $row($crawler, (string) $future->getTitle())->filter('summary [data-test="roadmap-outside"]')->attr('data-side'));
        self::assertCount(0, $row($crawler, (string) $past->getTitle())->filter('[data-test="roadmap-outside"]'));
        self::assertCount(1, $row($crawler, (string) $unsplit->getTitle()));
        self::assertCount(0, $row($crawler, (string) $unsplit->getTitle())->filter('[data-test="roadmap-outside"]'), 'A project without dates has no mark.');
    }

    public function testEachSignalSaysInATooltipWhatItMeansAndWhyItShows(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createProject();
        $this->createLot($project, 5, $this->createUser(), title: 'Sans début');

        $crawler = $client->request('GET', '/roadmap');

        $projectRow = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"]', $project->getTitle()));
        $leafSignal = $projectRow->filter('[data-title="Sans début"] [data-test="signal-without_start"]');
        $leafTooltip = $projectRow->filter('[data-title="Sans début"] [data-test="signal-tooltip-without_start"]');
        self::assertSame('button', $leafSignal->nodeName());
        self::assertSame($leafTooltip->attr('id'), $leafSignal->attr('data-roadmap-tooltip'));
        self::assertSame($leafTooltip->attr('id'), $leafSignal->attr('aria-describedby'));
        self::assertSame('Sans début', $leafTooltip->filter('p')->first()->text());
        self::assertSame(RoadmapSignal::WithoutStart->meaning(), $leafTooltip->filter('[data-test="signal-meaning"]')->text());
        self::assertSame('Pourquoi : ' . RoadmapSignal::WithoutStart->reason(), $leafTooltip->filter('[data-test="signal-reason"]')->text());

        $projectSignal = $projectRow->filter('summary [data-test="signal-partial_planning"]');
        $projectTooltip = $projectRow->filter('summary [data-test="signal-tooltip-partial_planning"]');
        self::assertSame('div', $projectSignal->nodeName(), 'A summary may not hold a button.');
        self::assertSame($projectTooltip->attr('id'), $projectSignal->attr('data-roadmap-tooltip'));
        self::assertSame('Pourquoi : ' . RoadmapSignal::PartialPlanning->reason(), $projectTooltip->filter('[data-test="signal-reason"]')->text());
    }

    public function testOverloadIsShownToLeadsAndAbsentFromThePageOfProd(): void
    {
        $alice = $this->createUser();
        $project = $this->createProject();
        $this->planLot($this->createLot($project, 10, $alice, title: 'Première'), new \DateTimeImmutable('2026-10-12'), [[$alice, 100]]);
        $this->planLot($this->createLot($project, 10, $alice, title: 'Seconde'), new \DateTimeImmutable('2026-10-19'), [[$alice, 50]]);
        $selector = \sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="signal-to_replan"]', $project->getTitle());

        $crawler = $this->clientAs('olivier.thomas@anyti.me')->request('GET', '/roadmap');
        self::assertCount(2, $crawler->filter($selector));

        $crawler = $this->clientAs('antoine.leroy@anyti.me')->request('GET', '/roadmap');
        self::assertCount(0, $crawler->filter($selector));
        self::assertStringNotContainsString('à replanifier', (string) $crawler->filter('[data-test="roadmap"]')->html());
    }

    public function testLeadOpensALeafFromTheRoadmapAndComesBackToTheSameWindow(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
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
        $client = $this->clientAs('olivier.thomas@anyti.me');

        $crawler = $client->request('GET', '/roadmap/2026-W50');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test="roadmap-period"]', 'Du 09/11/2026 au 22/08/2027');
        self::assertSame('/roadmap/2026-W46', $crawler->filter('[data-test="roadmap-previous"]')->attr('href'));
        self::assertSame('/roadmap/2027-W01', $crawler->filter('[data-test="roadmap-next"]')->attr('href'));
        self::assertSelectorNotExists('[data-test="roadmap-today"][aria-current="page"]');

        $client->request('GET', '/roadmap/2026-W60');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheBoardCountsTheLeavesAwaitingAnActionForPlannersOnly(): void
    {
        $project = $this->createProject();
        $this->createLot($project, title: 'Sans estimation');

        $crawler = $this->clientAs('olivier.thomas@anyti.me')->request('GET', '/roadmap');
        $counter = $crawler->filter('[data-test="roadmap-board-counter"][data-signal="to_estimate"]');
        self::assertCount(1, $counter);
        self::assertGreaterThanOrEqual(1, (int) $counter->filter('[data-test="roadmap-board-count"]')->text());
        self::assertSame('Poser une estimation', $counter->filter('[data-test="roadmap-board-action"]')->text());
        self::assertSame('/roadmap?signal=to_estimate', $counter->attr('href'));
        self::assertSelectorNotExists('[data-test="roadmap-board-counter"][aria-current]');
        self::assertSelectorNotExists('[data-test="roadmap-board-reset"]');

        $crawler = $this->clientAs('antoine.leroy@anyti.me')->request('GET', '/roadmap');
        self::assertCount(0, $crawler->filter('[data-test="roadmap-board"]'));
    }

    public function testTheBoardCountsTheSameWhateverTheWindowShown(): void
    {
        $client = $this->clientAs('thomas.cerqueus@anyti.me');
        $counters = static fn (Crawler $page): array => $page->filter('[data-test="roadmap-board-counter"]')->each(static fn (Crawler $counter): string => $counter->attr('data-signal') . ':' . $counter->filter('[data-test="roadmap-board-count"]')->text() . ':' . $counter->filter('[data-test="roadmap-board-projects"]')->text());

        $now = $counters($client->request('GET', '/roadmap'));

        self::assertNotSame([], $now);
        self::assertSame($now, $counters($client->request('GET', '/roadmap/2027-W30')));
    }

    public function testACounterLeavesOnlyItsLeavesOnTheFriezeOpenUnderTheirProject(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $alice = $this->createUser();
        $awaiting = $this->createProject();
        $leaf = $this->createLot($awaiting, title: 'Sans estimation');
        $this->planLot($this->createLot($awaiting, 5, title: 'Socle'), new \DateTimeImmutable('2026-10-12'), [[$alice, 50]]);
        $other = $this->createProject();
        $this->planLot($this->createLot($other, 5, title: 'Recette'), new \DateTimeImmutable('2026-10-12'), [[$alice, 50]]);

        $crawler = $client->request('GET', '/roadmap?signal=to_estimate');

        self::assertResponseIsSuccessful();
        $project = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"]', $awaiting->getTitle()));
        self::assertCount(1, $project->filter('details[open]:not([data-roadmap-target])'));
        self::assertSame(['Sans estimation'], $project->filter('[data-test="roadmap-leaf"]')->each(static fn (Crawler $row): string => (string) $row->attr('data-title')));
        self::assertCount(0, $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"]', $other->getTitle())));
        self::assertSelectorExists('[data-test="roadmap-board-counter"][data-signal="to_estimate"][aria-current="true"]');
        self::assertSame('/roadmap', $crawler->filter('[data-test="roadmap-board-counter"][data-signal="to_estimate"]')->attr('href'));
        self::assertSame('/roadmap', $crawler->filter('[data-test="roadmap-board-reset"]')->attr('href'));
        self::assertSame('/roadmap/2026-W37?signal=to_estimate', $crawler->filter('[data-test="roadmap-previous"]')->attr('href'));
        self::assertSame('/roadmap/2026-W45?signal=to_estimate', $crawler->filter('[data-test="roadmap-next"]')->attr('href'));
        self::assertSame('/roadmap?signal=to_estimate', $crawler->filter('[data-test="roadmap-today"]')->attr('href'));
        self::assertSame(\sprintf('/lots/%d/modifier?roadmap=2026-W41&signal=to_estimate', $leaf->getId()), $project->filter('[data-test="roadmap-leaf-link"]')->attr('href'));
        self::assertSame(\sprintf('/projets/%d?roadmap=2026-W41&signal=to_estimate', $awaiting->getId()), $project->filter('[data-test="roadmap-project-open"]')->attr('href'));

        $crawler = $client->request('GET', '/roadmap/2026-W50?signal=to_estimate');
        self::assertSame('/roadmap/2026-W50', $crawler->filter('[data-test="roadmap-board-reset"]')->attr('href'));
        self::assertSame('/roadmap/2026-W50', $crawler->filter('[data-test="roadmap-board-counter"][data-signal="to_estimate"]')->attr('href'));
    }

    #[DataProvider('ignoredFilterProvider')]
    public function testAFilterThatDoesNotApplyLeavesTheWholeRoadmap(string $email, string $signal): void
    {
        $project = $this->createProject();
        $this->createLot($project, title: 'Sans estimation');
        $whole = $this->createProject();

        $crawler = $this->clientAs($email)->request('GET', '/roadmap?signal=' . $signal);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"]', $whole->getTitle())));
        self::assertSame('/roadmap/2026-W37', $crawler->filter('[data-test="roadmap-previous"]')->attr('href'));
        self::assertCount(0, $crawler->filter('[data-test="roadmap-board-counter"][aria-current]'));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function ignoredFilterProvider(): \Generator
    {
        yield 'prod' => ['antoine.leroy@anyti.me', 'to_estimate'];
        yield 'signal without counter' => ['olivier.thomas@anyti.me', 'completed'];
        yield 'unknown signal' => ['olivier.thomas@anyti.me', 'nope'];
    }

    public function testAFilterWithNothingLeftSaysSoAndGivesTheWholeRoadmapBack(): void
    {
        self::mockTime('2000-01-03 10:00');

        $crawler = $this->clientAs('thomas.cerqueus@anyti.me')->request('GET', '/roadmap?signal=late_start');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test="roadmap-filter-empty"]', 'Rien à traiter pour « démarrage en retard ».', 'No start date has passed yet in 2000.');
        self::assertSame('/roadmap', $crawler->filter('[data-test="roadmap-filter-reset"]')->attr('href'));
        self::assertCount(0, $crawler->filter('[data-test="roadmap-project"]'));
    }

    public function testQueryCountDoesNotGrowWithTheNumberOfLeaves(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $small = $this->queryCount($client);

        $alice = $this->createUser();
        $project = $this->createProject();
        foreach (['2026-10-05', '2026-10-19', '2026-11-02'] as $start) {
            $this->planLot($this->createLot($project, 10, $alice), new \DateTimeImmutable($start), [[$alice, 100], [$this->createUser(), 50]]);
        }

        self::assertSame($small, $this->queryCount($client));
        self::assertSame($small, $this->queryCount($client, '/roadmap?signal=to_replan'), 'The board and the filter read the roadmap already built.');
    }

    private function queryCount(KernelBrowser $client, string $url = '/roadmap'): int
    {
        $client->enableProfiler();
        $client->request('GET', $url);
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
