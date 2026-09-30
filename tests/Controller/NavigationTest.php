<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class NavigationTest extends WebTestCase
{
    use CreatesProjects;
    use CreatesUsers;

    public function testTheDirectionSeesItsDailyEntriesOnTopAndTheAdministrationSectionBelow(): void
    {
        $crawler = $this->clientFor('admin@example.com')->request('GET', '/');

        self::assertSame(['nav-dashboard', 'nav-timesheet', 'nav-roadmap', 'nav-projects', 'nav-team', 'nav-holidays'], $this->entries($crawler->filter('#app-sidebar')));
        self::assertSame(['nav-projects', 'nav-team', 'nav-holidays'], $this->entries($crawler->filter('[data-test="nav-admin"]')));
        self::assertSelectorTextContains('[data-test="nav-admin"]', 'Administration');
        self::assertSame('Administration', $crawler->filter('#' . $crawler->filter('[data-test="nav-admin"] ul')->attr('aria-labelledby'))->text());
    }

    #[DataProvider('nonDirectorProvider')]
    public function testLeadsAndProdSeeOnlyProjectsInTheAdministrationSection(string $email): void
    {
        $crawler = $this->clientFor($email)->request('GET', '/');

        self::assertSame(['nav-dashboard', 'nav-timesheet', 'nav-roadmap', 'nav-projects'], $this->entries($crawler->filter('#app-sidebar')));
        self::assertSame(['nav-projects'], $this->entries($crawler->filter('[data-test="nav-admin"]')));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function nonDirectorProvider(): \Generator
    {
        yield 'lead' => ['lead@example.com'];
        yield 'prod' => ['prod@example.com'];
    }

    public function testTheEntryOfThePageShownIsCurrentIncludingOnSubPages(): void
    {
        $client = $this->clientFor('admin@example.com');
        $project = $this->createProject();
        $lot = $this->createLot($project);
        $member = $this->createUser();

        $pages = [
            '/' => 'nav-dashboard',
            '/saisie/2026-W40' => 'nav-timesheet',
            '/roadmap' => 'nav-roadmap',
            '/roadmap/2026-W40' => 'nav-roadmap',
            '/projets' => 'nav-projects',
            '/projets/' . $project->getId() => 'nav-projects',
            '/lots/' . $lot->getId() . '/modifier' => 'nav-projects',
            '/equipe/' . $member->getId() . '/modifier' => 'nav-team',
            '/jours-feries/2031' => 'nav-holidays',
        ];
        foreach ($pages as $url => $entry) {
            $crawler = $client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertSame([$entry], $this->currentEntries($crawler), $url);
        }

        $crawler = $client->request('GET', '/mon-compte/mot-de-passe');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->currentEntries($crawler));
    }

    public function testTheHomePageGroupsTheAdministrationShortcutsByRole(): void
    {
        $crawler = $this->clientFor('admin@example.com')->request('GET', '/');

        self::assertSelectorExists('[data-test="home-timesheet"]');
        self::assertSelectorExists('[data-test="home-account"]');
        self::assertSelectorTextContains('[data-test="home-admin"] h2', 'Administration');
        self::assertSame(['home-projects', 'home-team', 'home-holidays'], $this->shortcuts($crawler->filter('[data-test="home-admin"]')));

        $crawler = $this->clientFor('prod@example.com')->request('GET', '/');

        self::assertSame(['home-projects'], $this->shortcuts($crawler->filter('[data-test="home-admin"]')));
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

    /**
     * @return list<string>
     */
    private function shortcuts(Crawler $zone): array
    {
        return $zone->filter('a[data-test^="home-"]')->each(static fn (Crawler $link): string => (string) $link->attr('data-test'));
    }
}
