<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesUsers;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class ProjectControllerTest extends WebTestCase
{
    use CreatesProjects;
    use CreatesUsers;

    public function testProdBrowsesProjectsButCannotManageThem(): void
    {
        $client = $this->clientAs('prod@example.com');
        $project = $this->createProject();

        $client->request('GET', '/projets');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="project-new"]');

        $client->request('GET', '/projets/' . $project->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="project-edit"]');
        self::assertSelectorNotExists('[data-test="project-delete"]');

        foreach (['/projets/nouveau', '/projets/' . $project->getId() . '/modifier'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }

        $client->request('POST', '/projets/' . $project->getId() . '/supprimer');
        self::assertResponseStatusCodeSame(403);
    }

    public function testProjectsEntryIsShownToEveryone(): void
    {
        $client = $this->clientAs('prod@example.com');

        $client->request('GET', '/');

        self::assertSelectorExists('[data-test="nav-projects"]');
        self::assertSelectorExists('[data-test="home-projects"]');
    }

    public function testLeadCreatesAProject(): void
    {
        $client = $this->clientAs('lead@example.com');
        $title = uniqid('Nouveau projet ', true);

        $this->submitProjectForm($client, '/projets/nouveau', ['title' => $title, 'description' => 'Refonte du portail client.']);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test="project-heading"]', $title);
        self::assertSelectorTextContains('[data-test="project-description"]', 'Refonte du portail client.');
        self::assertSelectorExists('[data-test="signal-unsplit"]');
    }

    public function testProjectTitleMustBeUniqueIgnoringCase(): void
    {
        $client = $this->clientAs('lead@example.com');

        foreach (['KADENCE', 'évolution du portail'] as $title) {
            $this->submitProjectForm($client, '/projets/nouveau', ['title' => $title]);
            self::assertResponseStatusCodeSame(422, $title);
            self::assertSelectorTextContains('[data-test="project-form"]', 'Un projet porte déjà ce titre.');
        }
    }

    public function testLeadRenamesAProject(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $title = uniqid('Renommé ', true);

        $this->submitProjectForm($client, '/projets/' . $project->getId() . '/modifier', ['title' => $title]);

        self::assertResponseRedirects('/projets/' . $project->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test="project-heading"]', $title);
    }

    public function testRenamingAProjectToAnExistingTitleIsRefused(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();

        $this->submitProjectForm($client, '/projets/' . $project->getId() . '/modifier', ['title' => 'kadence']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="project-form"]', 'Un projet porte déjà ce titre.');
    }

    public function testListIsSortedAlphabeticallyWithAccentsFolded(): void
    {
        $client = $this->clientAs('prod@example.com');

        $crawler = $client->request('GET', '/projets');

        $titles = $crawler->filter('[data-test="project-row"]')->each(static fn ($row): string => (string) $row->attr('data-title'));
        $portal = array_search('Évolution du portail', $titles, true);
        $kadence = array_search('Kadence', $titles, true);
        $support = array_search('Support et maintenance', $titles, true);
        self::assertIsInt($portal);
        self::assertIsInt($kadence);
        self::assertIsInt($support);
        self::assertLessThan($kadence, $portal);
        self::assertLessThan($support, $kadence);
    }

    public function testListShowsTotalsAndWhatIsLeftToComplete(): void
    {
        $client = $this->clientAs('prod@example.com');

        $client->request('GET', '/projets');

        $kadence = '[data-title="Kadence"]';
        self::assertSelectorTextContains($kadence . ' [data-test="estimate"]', '38 j');
        self::assertSelectorExists($kadence . ' [data-test="estimate-partial"]');
        self::assertSelectorTextContains($kadence . ' [data-test="signal-to-estimate"]', '1 à estimer');
        self::assertSelectorTextContains($kadence . ' [data-test="signal-to-assign"]', '1 à désigner');
        self::assertSelectorTextContains($kadence . ' [data-test="signal-to-reassign"]', '1 à redésigner');
        self::assertSelectorExists('[data-title="Évolution du portail"] [data-test="signal-unsplit"]');
    }

    public function testMineFilterKeepsProjectsWhereIOwnALeaf(): void
    {
        $client = $this->clientAs('prod@example.com');

        $client->request('GET', '/projets?mes-responsabilites=1');

        self::assertSelectorExists('[data-title="Kadence"]');
        self::assertSelectorNotExists('[data-title="Support et maintenance"]');
        self::assertSelectorNotExists('[data-title="Évolution du portail"]');
    }

    public function testProjectPageShowsTheTreeAndHighlightsMyLeaves(): void
    {
        $client = $this->clientAs('prod@example.com');
        $crawler = $client->request('GET', '/projets');

        $client->click($crawler->filter('[data-title="Kadence"] [data-test="project-link"]')->link());

        self::assertSelectorExists('[data-test="lot-row"][data-title="Modèle et règles"][data-mine="true"]');
        self::assertSelectorExists('[data-test="lot-row"][data-title="Écrans"][data-mine="false"]');
        self::assertSelectorTextContains('[data-title="Projets et lots"] [data-test="lot-estimate-cell"]', '13 j');
        self::assertSelectorExists('[data-title="Saisie des temps"] [data-test="leaf-to-estimate"]');
        self::assertSelectorExists('[data-title="Roadmap"] [data-test="leaf-to-assign"]');
        self::assertSelectorExists('[data-title="Rappels de saisie"] [data-test="leaf-to-reassign"]');
    }

    public function testProjectPageQueryCountDoesNotGrowWithItsLots(): void
    {
        $client = $this->clientAs('prod@example.com');
        $owner = $this->fixtureUser('lead@example.com');
        $small = $this->createProject();
        $this->createLot($small, 3, $owner);
        $large = $this->createProject();
        for ($i = 0; $i < 4; ++$i) {
            $lot = $this->createLot($large, null, null);
            $this->createLot($large, 2, $owner, $lot);
            $this->createLot($large, 3, $owner, $lot);
        }

        // Warm-up request: the kernel reboots before the next ones, so the profiler no longer counts the fixture inserts above.
        $client->request('GET', '/');

        self::assertSame($this->queryCount($client, $small), $this->queryCount($client, $large));
    }

    public function testDeletingAProjectRemovesItsLotsAndSubLots(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $lot = $this->createLot($project);
        $subLot = $this->createLot($project, 2, null, $lot);
        $ids = [$lot->getId(), $subLot->getId()];
        $projectId = $project->getId();

        $crawler = $client->request('GET', '/projets/' . $projectId);
        self::assertSelectorTextContains('[data-test="project-delete-scope"]', '1 lot(s) et 1 sous-lot(s)');
        $client->submit($crawler->filter('[data-test="project-delete-confirm"]')->form());

        self::assertResponseRedirects('/projets');
        $entityManager = $this->entityManager();
        $entityManager->clear();
        self::assertNull($entityManager->find(Project::class, $projectId));
        foreach ($ids as $id) {
            self::assertNull($entityManager->find(Lot::class, $id));
        }
    }

    public function testDeletingWithAnInvalidCsrfTokenIsRefused(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();

        $client->request('POST', '/projets/' . $project->getId() . '/supprimer', ['_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
    }

    private function queryCount(KernelBrowser $client, Project $project): int
    {
        $client->enableProfiler();
        $client->request('GET', '/projets/' . $project->getId());
        self::assertResponseIsSuccessful();

        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }

    /**
     * @param array<string, string> $values
     */
    private function submitProjectForm(KernelBrowser $client, string $url, array $values): void
    {
        $crawler = $client->request('GET', $url);
        $fields = [];
        foreach ($values as $field => $value) {
            $fields['project[' . $field . ']'] = $value;
        }

        $client->submit($crawler->filter('[data-test="project-form"]')->form($fields));
    }

    private function clientAs(string $email): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser($this->fixtureUser($email));

        return $client;
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
