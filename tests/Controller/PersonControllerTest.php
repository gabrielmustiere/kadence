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
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class PersonControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    private const array RESERVED = ['person-role', 'person-tags-competence', 'person-load', 'person-free-from', 'person-load-row', 'person-manager'];

    protected function setUp(): void
    {
        self::mockTime('2026-10-02 10:00');
    }

    /**
     * @param list<string> $shown
     */
    #[DataProvider('viewerProvider')]
    public function testWhatEachRoleSeesOnThePageOfSomeoneElse(string $email, array $shown): void
    {
        $client = $this->clientAs($email);
        $alice = $this->createExample();

        $crawler = $client->request('GET', $this->url($alice));

        self::assertResponseIsSuccessful();
        self::assertSame($shown, self::reserved($crawler));
        self::assertSelectorExists('[data-test="person-projects"]');
        self::assertSelectorExists('[data-test="person-upcoming"]');
        self::assertSelectorExists('[data-test="timeline"]');
        self::assertCount(3, $crawler->filter('[data-test="person-leaf"]'));
        self::assertSame(\in_array('person-manager', $shown, true), str_contains((string) $client->getResponse()->getContent(), 'Martine'), 'The name of the manager only shows with the manager.');
    }

    /**
     * @return \Generator<string, array{string, list<string>}>
     */
    public static function viewerProvider(): \Generator
    {
        yield 'direction' => ['admin@example.com', self::RESERVED];
        yield 'lead' => ['lead@example.com', ['person-role', 'person-tags-competence', 'person-load', 'person-free-from', 'person-load-row']];
        yield 'prod' => ['prod@example.com', []];
    }

    public function testThePersonSeesEverythingOnTheirOwnPage(): void
    {
        $alice = $this->createExample();
        $client = $this->clientAs((string) $alice->getEmail());

        $crawler = $client->request('GET', $this->url($alice));

        self::assertSame(self::RESERVED, self::reserved($crawler));
    }

    public function testPageOfThePitch(): void
    {
        $client = $this->clientAs('lead@example.com');
        $alice = $this->createExample();

        $crawler = $client->request('GET', $this->url($alice));

        self::assertSelectorTextSame('[data-test="person-heading"]', 'Alice Martin');
        self::assertSame([], $crawler->filter('#app-sidebar [aria-current="page"]')->each(static fn (Crawler $link): string => (string) $link->attr('data-test')));
        self::assertCount(0, $crawler->filter('main a[href*="/modifier"], main form'));
        self::assertSame(
            [['Refonte', '10 j'], ['Support', '3 j']],
            $crawler->filter('[data-test="person-project"]')->each(static fn (Crawler $project): array => [(string) $project->attr('data-title'), $project->filter('[data-test="person-project-entered"]')->text()]),
        );
        self::assertSelectorTextSame('[data-test="person-load-next"]', '50 %');
        self::assertSelectorTextContains('[data-test="person-load"]', 'Lun 05/10/2026');
        self::assertSelectorTextSame('[data-test="person-free-from"]', 'Mar 27/10/2026');
        self::assertSame(['50'], $crawler->filter('[data-test="person-load-span"]')->each(static fn (Crawler $span): string => (string) $span->attr('data-percent')));

        self::assertSame(['Mobile', 'Refonte', 'Support'], $crawler->filter('[data-test="person-project-row"]')->each(static fn (Crawler $row): string => (string) $row->attr('data-title')));
        self::assertSame(['Front · Login', 'API', 'Maintenance'], $crawler->filter('[data-test="person-leaf"]')->each(static fn (Crawler $row): string => (string) $row->attr('data-title')));
        self::assertSame('50 %', $crawler->filter('[data-test="person-bar-share"]')->text());
        self::assertSame('Du 07/09/2026 au 01/11/2026', $crawler->filter('[data-test="roadmap-period"]')->text());

        $upcoming = $crawler->filter('[data-test="person-upcoming-entry"]');
        self::assertCount(1, $upcoming);
        self::assertSame('Front · Login', $upcoming->attr('data-leaf'));
        self::assertSame('50 %', $upcoming->filter('[data-test="person-upcoming-share"]')->text());
        self::assertSame('Lun 05/10/2026 → Lun 26/10/2026', $upcoming->filter('[data-test="person-upcoming-period"]')->text());

        self::assertSame(['Septembre 2026'], $crawler->filter('[data-test="timeline-month-label"]')->each(static fn (Crawler $label): string => $label->text()));
        self::assertSame(
            [['Refonte · API', '5 j'], ['Support · Maintenance', '3 j'], ['Refonte · API', '5 j']],
            $crawler->filter('[data-test="timeline-entry"]')->each(static fn (Crawler $entry): array => [$entry->filter('[data-test="timeline-entry-leaf"]')->text(), $entry->filter('[data-test="timeline-entry-entered"]')->text()]),
        );
        self::assertCount(0, $crawler->filter('[data-test="timeline"] [data-test="roadmap-team"]'), 'The journal of a person does not list who entered what.');

        $projectLinks = array_unique($crawler->filter('[data-test="person-project-link"]')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertCount(3, $projectLinks);
        foreach ($projectLinks as $href) {
            self::assertMatchesRegularExpression('#^/roadmap/projets/\d+$#', $href);
        }
    }

    public function testOverloadIsFlaggedToLeadsOnly(): void
    {
        $alice = $this->createUser();
        $project = $this->createProject();
        foreach (['Première', 'Seconde'] as $title) {
            $this->planLot($this->createLot($project, 5, $alice, title: $title), new \DateTimeImmutable('2026-10-05'), [[$alice, 100]]);
        }

        $crawler = $this->clientAs('lead@example.com')->request('GET', $this->url($alice));
        self::assertCount(2, $crawler->filter('[data-test="person-leaf"] [data-test="signal-to_replan"]'));
        self::assertSame(['200'], $crawler->filter('[data-test="person-load-span"]')->each(static fn (Crawler $span): string => (string) $span->attr('data-percent')));
        self::assertStringContainsString('bg-danger-soft', (string) $crawler->filter('[data-test="person-load-span"]')->attr('class'), 'The days beyond a full load stand out.');

        $crawler = $this->clientAs('prod@example.com')->request('GET', $this->url($alice));
        self::assertCount(0, $crawler->filter('[data-test="signal-to_replan"]'));
        self::assertCount(0, $crawler->filter('[data-test="person-load-span"]'));
    }

    public function testFreeDayIsUnknownWhileALeafOfTheirTeamsHasNoEnd(): void
    {
        $client = $this->clientAs('lead@example.com');
        $alice = $this->createExample();
        $this->createLot($this->createProject('Zèbre'), 5, $alice, title: 'Sans début');

        $crawler = $client->request('GET', $this->url($alice));

        self::assertSelectorTextSame('[data-test="person-free-from"]', 'inconnue (Zèbre · Sans début)');
        self::assertSame(['Front · Login', 'Sans début'], $crawler->filter('[data-test="person-upcoming-entry"]')->each(static fn (Crawler $entry): string => (string) $entry->attr('data-leaf')));
        self::assertCount(1, $crawler->filter('[data-test="person-upcoming-entry"]')->last()->filter('[data-test="signal-without_start"]'));
    }

    public function testDeactivatedPersonKeepsTheirPageWithoutLoad(): void
    {
        $client = $this->clientAs('lead@example.com');
        $alice = $this->createExample();
        $alice->setActive(false);
        $this->entityManager()->flush();

        $crawler = $client->request('GET', $this->url($alice));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-test="person-inactive"]', 'désactivée');
        self::assertSelectorTextSame('[data-test="person-load"]', 'Désactivée, aucune charge');
        self::assertSelectorNotExists('[data-test="person-free-from"]');
        self::assertSelectorNotExists('[data-test="person-load-row"]');
        self::assertCount(3, $crawler->filter('[data-test="timeline-entry"]'));
    }

    public function testPersonWithNothingSaysSo(): void
    {
        $client = $this->clientAs('lead@example.com');
        $nobody = $this->createUser();

        $client->request('GET', $this->url($nobody));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="person-projects-empty"]');
        self::assertSelectorExists('[data-test="person-no-frieze"]');
        self::assertSelectorNotExists('[data-test="roadmap"]');
        self::assertSelectorNotExists('[data-controller="roadmap"]');
        self::assertSelectorExists('[data-test="person-upcoming-empty"]');
        self::assertSelectorTextSame('[data-test="timeline-empty"]', 'Aucun temps saisi.');
        self::assertSelectorTextSame('[data-test="person-free-from"]', 'aucune affectation à venir');
    }

    public function testUnknownPersonIsNotFound(): void
    {
        $this->clientAs('prod@example.com')->request('GET', '/personnes/999999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testQueryCountDoesNotGrowWithTheNumberOfLeaves(): void
    {
        $client = $this->clientAs('lead@example.com');
        $few = $this->createUser();
        $many = $this->createUser();
        $leaf = $this->planLot($this->createLot($this->createProject(), 10, $few), new \DateTimeImmutable('2026-09-28'), [[$few, 100]]);
        $this->createTimeEntry($few, $leaf, '2026-09-28', 4);
        foreach (['2026-09-28', '2026-10-05', '2026-10-19', '2026-11-02'] as $start) {
            $leaf = $this->planLot($this->createLot($this->createProject(), 10, $many), new \DateTimeImmutable($start), [[$many, 50], [$this->createUser(), 50]]);
            $this->createTimeEntry($many, $leaf, '2026-09-29', 2);
        }
        $this->queryCount($client, $few);

        self::assertSame($this->queryCount($client, $few), $this->queryCount($client, $many));
    }

    /**
     * The example of the pitch: Alice, managed by Martine and tagged, at 100 % on « API » (Refonte, 10 j) entered in full
     * on 07/09–11/09 and 21/09–25/09, on « Maintenance » (Support) outside its team on 14/09–16/09, and at 50 % on
     * « Login » (Mobile, lot Front, 8 j) from 05/10.
     */
    private function createExample(): User
    {
        $alice = $this->createUser()->setFirstName('Alice')->setLastName('Martin');
        $alice->setManager($this->createUser()->setFirstName('Martine'));
        $this->entityManager()->flush();

        $api = $this->planLot($this->createLot($this->createProject('Refonte'), 10, $alice, title: 'API'), new \DateTimeImmutable('2026-09-07'), [[$alice, 100]]);
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25'] as $day) {
            $this->createTimeEntry($alice, $api, $day, 4);
        }
        $maintenance = $this->createLot($this->createProject('Support'), 20, title: 'Maintenance');
        foreach (['2026-09-14', '2026-09-15', '2026-09-16'] as $day) {
            $this->createTimeEntry($alice, $maintenance, $day, 4);
        }
        $mobile = $this->createProject('Mobile');
        $this->planLot($this->createLot($mobile, 8, parent: $this->createLot($mobile, title: 'Front'), title: 'Login'), new \DateTimeImmutable('2026-10-05'), [[$alice, 50]]);

        return $alice;
    }

    /**
     * @return list<string> the reserved parts of the page shown
     */
    private static function reserved(Crawler $crawler): array
    {
        return array_values(array_filter(self::RESERVED, static fn (string $test): bool => $crawler->filter(\sprintf('[data-test="%s"]', $test))->count() > 0));
    }

    private function url(User $person): string
    {
        return '/personnes/' . $person->getId();
    }

    private function queryCount(KernelBrowser $client, User $person): int
    {
        $client->enableProfiler();
        $client->request('GET', $this->url($person));
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
