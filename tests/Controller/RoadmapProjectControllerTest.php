<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Lot;
use App\Entity\Project;
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

final class RoadmapProjectControllerTest extends WebTestCase
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
    public function testLeadAndDirectionManageTheWholeProjectFromItsPage(string $email): void
    {
        $client = $this->clientAs($email);
        $project = $this->createExample();

        $crawler = $client->request('GET', $this->url($project));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-test="project-page-heading"]', (string) $project->getTitle());
        self::assertSame(['nav-projects'], $crawler->filter('#app-sidebar [aria-current="page"]')->each(static fn (Crawler $link): string => (string) $link->attr('data-test')));
        foreach (['project-edit' => 1, 'project-delete' => 1, 'lot-new' => 1, 'lot-edit' => 3, 'lot-new-sub-lot' => 2, 'lot-delete' => 3, 'progress-form' => 2] as $action => $count) {
            self::assertCount($count, $crawler->filter(\sprintf('main [data-test="%s"]', $action)), $action);
        }
        self::assertCount(0, $crawler->filter('[data-test="roadmap-leaf-link"]'), 'The title of a leaf never links to its edition here.');
        self::assertCount(2, $crawler->filter('[data-test="roadmap-leaf-title"]'));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function roleProvider(): \Generator
    {
        yield 'direction' => ['thomas.cerqueus@anyti.me'];
        yield 'lead' => ['olivier.thomas@anyti.me'];
    }

    public function testProdReadsThePageWithoutAnyActionButOnTheLeafTheyHold(): void
    {
        $project = $this->createExample();
        $client = $this->clientAs('antoine.leroy@anyti.me');

        $crawler = $client->request('GET', $this->url($project));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('main form, main a[href*="/modifier"], main a[href*="/nouveau"], main [data-test="lot-delete"]'), 'Responsible for no lot of the project, prod sees no action.');

        $login = $this->lot($project, 'Login');
        $owner = $login->getOwner();
        self::assertNotNull($owner);
        $crawler = $this->clientOf($owner)->request('GET', $this->url($project));

        $mine = $crawler->filter('[data-test="lot-row"][data-title="Login"]');
        self::assertCount(1, $mine->filter('[data-test="lot-edit"]'));
        self::assertCount(1, $mine->filter('[data-test="progress-form"]'));
        self::assertCount(1, $crawler->filter('main [data-test="lot-edit"]'));
        self::assertCount(1, $crawler->filter('main form'));
        foreach (['project-edit', 'project-delete', 'lot-new', 'lot-new-sub-lot', 'lot-delete'] as $action) {
            self::assertCount(0, $crawler->filter(\sprintf('main [data-test="%s"]', $action)), $action);
        }
    }

    public function testSummaryAndConsumptionTellWhereTheProjectStands(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createExample();

        $crawler = $client->request('GET', $this->url($project));

        self::assertSame(
            ['18 j', '13 j', '5 j', '0 j', 'Lun 07/09/2026', 'Mer 14/10/2026'],
            array_map(static fn (string $field): string => $crawler->filter(\sprintf('[data-test="project-page-%s"]', $field))->text(), ['estimate', 'entered', 'remaining', 'overrun', 'start', 'end']),
        );
        self::assertSelectorTextSame('[data-test="project-page-projected"]', '18 j');
        self::assertSelectorNotExists('[data-test="project-page-gap"]', 'Projected on its estimate, the project shows no gap.');
        self::assertSelectorNotExists('[data-test="project-page-at-least"]');
        self::assertSelectorNotExists('[data-test="project-page-projected-partial"]');
        self::assertSelectorTextContains('[data-test="project-page-no-offset"]', 'ne compense jamais le dépassement');
        self::assertSame([
            'API' => ['10 j', '10 j', '0 j'],
            'Front' => ['8 j', '3 j', '5 j'],
            'Login' => ['8 j', '3 j', '5 j'],
            'Projet' => ['18 j', '13 j', '5 j'],
        ], self::consumption($crawler));
    }

    public function testCumulKeepsWhatRemainsApartFromWhatGoesBeyond(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
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

        $row = $crawler->filter('[data-test="lot-row"][data-title="Back-office"]');
        self::assertSame('3 j', $row->filter('[data-test="consumption-remaining"]')->text());
        self::assertSame('5 j (50 %) de dépassement', $row->filter('[data-test="consumption-overrun"]')->text());
        self::assertSame('5 j (100 %) de dépassement', $crawler->filter('[data-test="lot-row"][data-title="Dépassé"] [data-test="consumption-overrun"]')->text());
        self::assertCount(0, $crawler->filter('[data-test="lot-row"][data-title="Dépassé"] [data-test="consumption-remaining"]'));
        self::assertSame(['3 j', '5 j (50 %)'], [$crawler->filter('[data-test="project-page-remaining"]')->text(), $crawler->filter('[data-test="project-page-overrun"]')->text()]);
    }

    public function testProgressRulesWhatIsLeftAndShowsTheProjectedCostAndTheDeclarationsOfEachLeaf(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createExample();

        $client->request('GET', $this->url($project));
        self::assertSelectorExists('[data-test="progress-history-empty"]');

        $login = $project->getLots()->filter(static fn ($lot): bool => 'Login' === $lot->getTitle())->first();
        self::assertNotFalse($login);
        $author = $this->createUser();
        $this->createProgress($login, $author, '2026-10-01', 20, 12);
        $this->createProgress($login, $author, '2026-10-05', 25, 12);

        $crawler = $client->request('GET', $this->url($project));

        self::assertSame(['9 j', 'Mar 20/10/2026'], [$crawler->filter('[data-test="project-page-remaining"]')->text(), $crawler->filter('[data-test="project-page-end"]')->text()]);
        self::assertSame(['8 j', '3 j', '9 j'], self::consumption($crawler)['Login']);
        $progress = static fn (string $title, string $field): string => $crawler->filter(\sprintf('[data-test="lot-row"][data-title="%s"] [data-test="consumption-%s"]', $title, $field))->text();
        self::assertSame(['25 % au 05/10', '12 j +4 j (+50 %)'], [$progress('Login', 'progress'), $progress('Login', 'projected')]);
        self::assertSame(['—', '—'], [$progress('API', 'progress'), $progress('API', 'projected')]);
        self::assertSame('25 %', $progress('Front', 'progress'));
        self::assertSame('67 %', $crawler->filter('[data-test="lot-total-row"] [data-test="consumption-progress"]')->text(), '10 j entered on API weigh 100 %, Login 25 % of its 8 j.');

        $history = $crawler->filter('[data-test="progress-history-leaf"][data-title="Login"]');
        self::assertStringContainsString('Front · Login', $history->text());
        self::assertSame(['Lun 05/10/2026 25 % Test User', 'Jeu 01/10/2026 20 % Test User'], $history->filter('[data-test="progress-history-entry"]')->each(static fn (Crawler $entry): string => $entry->text()));
        self::assertCount(1, $crawler->filter('[data-test="progress-history-leaf"]'));
        self::assertCount(0, $crawler->filter('main form'), 'Prod declares the progress of the leaves they hold only.');
    }

    public function testLeafToEstimateIsPartOfTheTableAndOfTheTimelineWithoutABarOnTheFrieze(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $alice = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->createLot($project, null, $alice, title: 'Cadrage');
        $this->createTimeEntry($alice, $leaf, '2026-09-30', 4);

        $crawler = $client->request('GET', $this->url($project));

        $row = $crawler->filter('[data-test="lot-row"][data-title="Cadrage"]');
        self::assertCount(1, $row->filter('[data-test="leaf-to-estimate"]'));
        self::assertSame('1 j', $row->filter('[data-test="consumption-entered"]')->text());
        self::assertCount(0, $row->filter('[data-test="consumption-remaining"]'));
        self::assertCount(1, $crawler->filter('[data-test="project-page-estimate"] [data-test="estimate-partial"]'));
        self::assertSame('Estimation partielle : 1 lot reste à estimer, et leur restant n\'est pas compté dans le total projeté.', $crawler->filter('[data-test="project-page-projected-partial"]')->text());
        self::assertCount(0, $crawler->filter('[data-test="roadmap-leaf"][data-title="Cadrage"] [data-test^="roadmap-bar"]'));
        self::assertSame(['Cadrage'], $crawler->filter('[data-test="timeline-entry-leaf"]')->each(static fn (Crawler $leafPath): string => $leafPath->text()));
    }

    public function testSignalsOfTheHeaderAndOfTheFriezeEachHaveTheirTooltip(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $alice = $this->createUser();
        $project = $this->createProject();
        $this->createTimeEntry($alice, $this->createLot($project, 5, $alice, title: 'Sans début'), '2026-09-30', 4);

        $crawler = $client->request('GET', $this->url($project));

        $headerSignal = $crawler->filter('header [data-controller="roadmap-tooltip"] [data-test="signal-partial_planning"]');
        $friezeSignal = $crawler->filter('[data-test="roadmap"] [data-test="signal-partial_planning"]');
        self::assertSame('button', $headerSignal->nodeName());
        self::assertSame('button', $friezeSignal->nodeName());
        self::assertNotSame($headerSignal->attr('data-roadmap-tooltip'), $friezeSignal->attr('data-roadmap-tooltip'), 'The project shows its signals twice on the page.');
        foreach ([$headerSignal, $friezeSignal] as $signal) {
            self::assertSame('Pourquoi : ' . RoadmapSignal::PartialPlanning->reason(), $crawler->filter('#' . $signal->attr('data-roadmap-tooltip') . ' [data-test="signal-reason"]')->text());
        }
        self::assertSame(RoadmapSignal::WithoutStart->meaning(), $crawler->filter('[data-test="roadmap-leaf"][data-title="Sans début"] [data-test="signal-tooltip-without_start"] [data-test="signal-meaning"]')->text());
    }

    public function testFriezeSpansTheProjectAndOpensUnzoomedWithoutRememberingItsZoom(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
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
        $client = $this->clientAs('antoine.leroy@anyti.me');
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
        $client = $this->clientAs('antoine.leroy@anyti.me');
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

    public function testTimelineFoldsTheMonthsBeyondTheThreeLatest(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $alice = $this->createUser();
        $project = $this->createProject();
        $leaf = $this->createLot($project, 300, $alice, title: 'Socle');
        foreach (['2026-05-04', '2026-06-01', '2026-07-06', '2026-08-03', '2026-09-07'] as $day) {
            $this->createTimeEntry($alice, $leaf, $day, 4);
        }

        $crawler = $client->request('GET', $this->url($project));

        $label = static fn (Crawler $month): string => $month->text();
        self::assertSame(['Septembre 2026', 'Août 2026', 'Juillet 2026', 'Juin 2026', 'Mai 2026'], $crawler->filter('[data-test="timeline-month-label"]')->each($label));
        $earlier = $crawler->filter('[data-test="timeline-earlier"]');
        self::assertSame('details', $earlier->nodeName());
        self::assertNull($earlier->attr('open'));
        $toggle = $earlier->filter('[data-test="timeline-earlier-toggle"]');
        self::assertStringEndsWith('les mois précédents (2)', trim($toggle->text()));
        self::assertSame(['Afficher', 'Masquer'], $toggle->filter('span')->each(static fn (Crawler $word): string => $word->text()), 'The word follows the state of the details, in CSS.');
        self::assertSame(['Juin 2026', 'Mai 2026'], $earlier->filter('[data-test="timeline-month-label"]')->each($label));
    }

    public function testTimelineOfThreeMonthsHasNothingToFold(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createExample();

        $client->request('GET', $this->url($project));

        self::assertSelectorExists('[data-test="timeline-month"]');
        self::assertSelectorNotExists('[data-test="timeline-earlier"]');
    }

    public function testRunBeyondTheEstimateIsMarkedOnTheTimeline(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
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

    public function testNamesOfTheTimelineLeadToThePageOfEachPersonButNotThoseOfTheTooltips(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createExample();
        $carol = $this->createUser();
        $api = $project->getLots()->first();
        self::assertNotFalse($api);
        $this->createTimeEntry($carol, $api, '2026-09-25', 1);

        $crawler = $client->request('GET', $this->url($project));

        $links = $crawler->filter('[data-test="timeline-entry"]')->first()->filter('[data-test="person-link"]');
        self::assertSame(2, $links->count(), 'The member, then Carol outside the team.');
        self::assertSame('/personnes/' . $carol->getId(), $links->last()->attr('href'));
        self::assertCount(0, $crawler->filter('[role="tooltip"] a'));
    }

    public function testComesBackToTheWeekOfTheRoadmapItWasOpenedFrom(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $project = $this->createProject();
        $this->createLot($project, 5, title: 'Socle');

        $crawler = $client->request('GET', '/roadmap/2026-W50');
        $link = $crawler->filter(\sprintf('[data-test="roadmap-project"][data-title="%s"] [data-test="roadmap-project-open"]', $project->getTitle()));
        self::assertSame($this->url($project) . '?roadmap=2026-W50', $link->attr('href'));
        self::assertSame('Ouvrir la fiche de ' . $project->getTitle(), $link->attr('aria-label'));

        $crawler = $client->request('GET', (string) $link->attr('href'));
        $back = $crawler->filter('[data-test="project-page-back"]');
        self::assertSame(['/roadmap/2026-W50', 'Roadmap'], [$back->attr('href'), $back->text()]);

        $crawler = $client->request('GET', $this->url($project) . '?roadmap=2026-W60');
        self::assertResponseIsSuccessful();
        $back = $crawler->filter('[data-test="project-page-back"]');
        self::assertSame(['/projets', 'Projets'], [$back->attr('href'), $back->text()]);
    }

    public function testComesBackToTheListOfProjectsItWasOpenedFrom(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createExample();

        $crawler = $client->request('GET', $this->url($project));

        $back = $crawler->filter('[data-test="project-page-back"]');
        self::assertSame(['/projets', 'Projets'], [$back->attr('href'), $back->text()]);
        self::assertSame(['nav-projects'], $crawler->filter('#app-sidebar [aria-current="page"]')->each(static fn (Crawler $link): string => (string) $link->attr('data-test')));
    }

    public function testTheFormerAddressOfThePageRedirectsToItKeepingTheWeekOfTheRoadmap(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createProject();

        $client->request('GET', '/roadmap/projets/' . $project->getId() . '?roadmap=2026-W50');
        self::assertResponseRedirects($this->url($project) . '?roadmap=2026-W50', 301);

        $client->request('GET', '/roadmap/projets/' . $project->getId());
        self::assertResponseRedirects($this->url($project), 301);

        $client->request('GET', '/roadmap/projets/' . $project->getId() . '?roadmap=2026-W60');
        self::assertResponseRedirects($this->url($project), 301, 'An invalid week is not carried over.');
    }

    public function testProjectWithoutDateHasNoFriezeNorTimeline(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createProject();
        $this->createLot($project, 5, $this->createUser(), title: 'Socle');

        $client->request('GET', $this->url($project));

        self::assertSelectorExists('[data-test="lots-table"]');
        self::assertSelectorExists('[data-test="project-page-no-frieze"]');
        self::assertSelectorNotExists('[data-test="roadmap"]');
        self::assertSelectorNotExists('[data-controller="roadmap"]');
        self::assertSelectorExists('[data-test="timeline-empty"]');
    }

    public function testUnsplitProjectSaysSoOnly(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createProject();

        $client->request('GET', $this->url($project));

        self::assertSelectorTextContains('[data-test="project-page-unsplit"]', 'pas encore découpé');
        self::assertSelectorNotExists('[data-test="project-page-summary"]');
        self::assertSelectorNotExists('[data-test="lots-table"]');
        self::assertSelectorNotExists('[data-test="roadmap"]');
        self::assertSelectorNotExists('[data-test="timeline"]');
        self::assertSelectorNotExists('[data-test="timeline-empty"]');
        self::assertSelectorNotExists('[data-test="lot-new"]');

        $this->clientAs('olivier.thomas@anyti.me')->request('GET', $this->url($project));
        self::assertSelectorExists('[data-test="lot-new"]');
    }

    public function testAnUnknownEndNamesTheLeavesWithoutOneAndWhy(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createExample();
        $client->request('GET', $this->url($project));
        self::assertSelectorNotExists('[data-test="unknown-ends"]');

        $login = $project->getLots()->filter(static fn ($lot): bool => 'Login' === $lot->getTitle())->first();
        self::assertNotFalse($login);
        $bob = $login->getOwner();
        self::assertNotNull($bob);
        foreach (['2026-09-17', '2026-09-18', '2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01'] as $day) {
            $this->createTimeEntry($bob, $login, $day, 4);
        }

        $crawler = $client->request('GET', $this->url($project));

        self::assertSelectorTextSame('[data-test="project-page-end"]', 'inconnue');
        self::assertSame(['Front · Login : en dépassement'], $crawler->filter('[data-test="project-page-summary"] [data-test="unknown-end"]')->each(static fn (Crawler $leaf): string => $leaf->text()));
    }

    public function testEachLotOfTheFriezeWritesItsEndButNotTheLineOfTheProject(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createExample();

        $crawler = $client->request('GET', $this->url($project));

        $frieze = $crawler->filter('[data-test="roadmap"]');
        self::assertCount(0, $frieze->filter('[data-test="roadmap-project"] [data-test="roadmap-end"]'));
        foreach (['API', 'Login'] as $title) {
            $leaf = $frieze->filter(\sprintf('[data-test="roadmap-leaf"][data-title="%s"]', $title));
            self::assertSame('Fin calculée : ' . $leaf->filter('[data-test="roadmap-recap-end"]')->text(), $leaf->filter('[data-test="roadmap-end"]')->text());
        }
        self::assertSame('known', $frieze->filter('[data-test="roadmap-lot"][data-title="Front"] [data-test="roadmap-end"]')->attr('data-state'));
        self::assertSame($frieze->filter('[data-test="roadmap-leaf"][data-title="Login"] [data-test="roadmap-end"] time')->text(), $crawler->filter('[data-test="project-page-end"]')->text());
    }

    public function testTheProjectedCostIsAMinimumWhileALeafOverrunsWithoutProgress(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createExample();
        $login = $this->lot($project, 'Login');
        $bob = $login->getOwner();
        self::assertNotNull($bob);
        foreach (['2026-09-17', '2026-09-18', '2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01'] as $day) {
            $this->createTimeEntry($bob, $login, $day, 4);
        }

        $crawler = $client->request('GET', $this->url($project));

        self::assertSelectorTextSame('[data-test="project-page-at-least"]', 'au moins');
        self::assertSelectorTextSame('[data-test="project-page-projected"]', '19 j');
        self::assertSelectorTextSame('[data-test="project-page-gap"]', '+1 j (+6 %)');
        self::assertSelectorExists('[data-test="project-page-at-least-reason"]');
        self::assertSame(['19 j', '0 j', '1 j (6 %)'], array_map(static fn (string $field): string => $crawler->filter(\sprintf('[data-test="project-page-%s"]', $field))->text(), ['entered', 'remaining', 'overrun']));

        $this->createProgress($login, $bob, '2026-10-05', 90, 36);
        $client->request('GET', $this->url($project));

        self::assertSelectorNotExists('[data-test="project-page-at-least"]', 'Its progress tells what is left on the leaf.');
        self::assertSelectorNotExists('[data-test="project-page-at-least-reason"]');
        self::assertSelectorTextSame('[data-test="project-page-projected"]', '20 j');
    }

    public function testUnknownProjectIsNotFound(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');

        foreach (['/projets/999999', '/roadmap/projets/999999'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testQueryCountDoesNotGrowWithTheNumberOfLeaves(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
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
        $crawler->filter('[data-test="lot-row"], [data-test="lot-total-row"]')->each(static function (Crawler $row) use (&$lines): void {
            $lines[(string) $row->attr('data-title')] = array_map(static fn (string $field): string => trim($row->filter(\sprintf('[data-test="%s"]', $field))->text()), ['consumption-estimate', 'consumption-entered', 'consumption-remaining']);
        });

        return $lines;
    }

    private function url(Project $project): string
    {
        return '/projets/' . $project->getId();
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

    private function lot(Project $project, string $title): Lot
    {
        $lot = $project->getLots()->findFirst(static fn (int $key, Lot $lot): bool => $title === $lot->getTitle());
        self::assertInstanceOf(Lot::class, $lot);

        return $lot;
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

    private function clientOf(User $user): KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->loginUser($user);

        return $client;
    }
}
