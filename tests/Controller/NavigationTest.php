<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeOff;
use App\Tests\Support\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class NavigationTest extends WebTestCase
{
    use CreatesProjects;
    use CreatesTimeOff;
    use CreatesUsers;

    public function testTheDirectionSeesTheDailyEntriesThenSteeringThenAdministration(): void
    {
        $crawler = $this->clientFor('thomas.cerqueus@anyti.me')->request('GET', '/');

        self::assertSame(['nav-home', 'nav-timesheet', 'nav-time-off', 'nav-roadmap', 'nav-projects', 'nav-people', 'nav-tags', 'nav-holidays', 'nav-entitlements'], $this->entries($crawler->filter('#app-sidebar')));
        self::assertSame(['nav-roadmap', 'nav-projects', 'nav-people'], $this->entries($crawler->filter('[data-test="nav-steering"]')));
        self::assertSame(['nav-tags', 'nav-holidays', 'nav-entitlements'], $this->entries($crawler->filter('[data-test="nav-admin"]')));
        self::assertSame('Pilotage', $crawler->filter('#' . $crawler->filter('[data-test="nav-steering"] ul')->attr('aria-labelledby'))->text());
        self::assertSame('Administration', $crawler->filter('#' . $crawler->filter('[data-test="nav-admin"] ul')->attr('aria-labelledby'))->text());
    }

    #[DataProvider('nonDirectorProvider')]
    public function testLeadsAndProdSeeSteeringButNoAdministration(string $email): void
    {
        $crawler = $this->clientFor($email)->request('GET', '/');

        self::assertSame(['nav-home', 'nav-timesheet', 'nav-time-off', 'nav-roadmap', 'nav-projects', 'nav-people'], $this->entries($crawler->filter('#app-sidebar')));
        self::assertSame(['nav-roadmap', 'nav-projects', 'nav-people'], $this->entries($crawler->filter('[data-test="nav-steering"]')));
        self::assertSelectorNotExists('[data-test="nav-admin"]');
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function nonDirectorProvider(): \Generator
    {
        yield 'lead' => ['olivier.thomas@anyti.me'];
        yield 'prod' => ['antoine.leroy@anyti.me'];
    }

    public function testTheEntryOfThePageShownIsCurrentIncludingOnSubPages(): void
    {
        $client = $this->clientFor('thomas.cerqueus@anyti.me');
        $project = $this->createProject();
        $lot = $this->createLot($project);
        $member = $this->createUser();

        $pages = [
            '/' => 'nav-home',
            '/saisie/2026-W40' => 'nav-timesheet',
            '/conges' => 'nav-time-off',
            '/conges/absences/2026-11' => 'nav-time-off',
            '/conges/compteurs' => 'nav-entitlements',
            '/conges/compteurs/rtt/2026' => 'nav-entitlements',
            '/roadmap' => 'nav-roadmap',
            '/roadmap/2026-W40' => 'nav-roadmap',
            '/projets' => 'nav-projects',
            '/projets/' . $project->getId() => 'nav-projects',
            '/lots/' . $lot->getId() . '/modifier' => 'nav-projects',
            '/personnes' => 'nav-people',
            '/personnes/' . $member->getId() => 'nav-people',
            '/personnes/nouveau' => 'nav-people',
            '/personnes/' . $member->getId() . '/modifier' => 'nav-people',
            '/jours-feries/2031' => 'nav-holidays',
            '/tags' => 'nav-tags',
        ];
        foreach ($pages as $url => $entry) {
            $crawler = $client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertSame([$entry], $this->currentEntries($crawler), $url);
        }

        foreach (['/mon-compte/mot-de-passe'] as $url) {
            $crawler = $client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertSame([], $this->currentEntries($crawler), $url);
        }
    }

    public function testTheTimeOffEntryCountsTheRequestsToDecideOn(): void
    {
        $client = self::createClient();
        $manager = $this->createUser(Role::Lead);
        $this->createTimeOff($this->createUser()->setManager($manager), '2026-11-16', '2026-11-20', TimeOffStatus::Pending);
        $this->createTimeOff($this->createUser()->setManager($manager), '2026-12-07', '2026-12-07', TimeOffStatus::Pending);
        $this->createTimeOff($this->createUser()->setManager($manager), '2026-12-14', '2026-12-14');

        $client->loginUser($manager);
        $client->request('GET', '/');
        self::assertSelectorTextSame('[data-test="nav-time-off"] [data-test="nav-count"]', '2 à valider');

        $client->loginUser($this->createUser());
        $client->request('GET', '/');
        self::assertSelectorNotExists('[data-test="nav-time-off"] [data-test="nav-count"]');
    }

    public function testTheAccountMenuLeadsToMyPage(): void
    {
        $client = $this->clientFor('antoine.leroy@anyti.me');
        $crawler = $client->request('GET', '/');

        $crawler = $client->click($crawler->filter('[data-test="nav-person"]')->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-test="person-heading"]', $crawler->filter('[data-test="user-menu-name"]')->text());
    }

    private function clientFor(string $email): KernelBrowser
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

    /**
     * @return list<string>
     */
    private function entries(Crawler $zone): array
    {
        return $zone->filter('a[data-test^="nav-"]')->each(static fn (Crawler $link): string => (string) $link->attr('data-test'));
    }

    /**
     * @return list<string>
     */
    private function currentEntries(Crawler $crawler): array
    {
        return $crawler->filter('#app-sidebar [aria-current="page"]')->each(static fn (Crawler $link): string => (string) $link->attr('data-test'));
    }
}
