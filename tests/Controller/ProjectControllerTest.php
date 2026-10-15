<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class ProjectControllerTest extends WebTestCase
{
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    public function testProdBrowsesProjectsButCannotManageThem(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createProject();

        $client->request('GET', '/projets');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="project-new"]');

        $this->createLot($project, 3, $this->createUser(), title: 'Socle');
        $client->request('GET', '/projets/' . $project->getId());
        self::assertResponseIsSuccessful();
        foreach (['project-edit', 'project-delete', 'lot-new', 'lot-edit', 'lot-new-sub-lot', 'lot-delete', 'progress-form'] as $action) {
            self::assertSelectorNotExists(\sprintf('[data-test="%s"]', $action), $action);
        }

        foreach (['/projets/nouveau', '/projets/' . $project->getId() . '/modifier'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }

        $client->request('POST', '/projets/' . $project->getId() . '/supprimer');
        self::assertResponseStatusCodeSame(403);
    }

    public function testProjectsEntryIsShownToEveryone(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');

        $client->request('GET', '/');

        self::assertSelectorExists('[data-test="nav-projects"]');
    }

    public function testLeadCreatesAProject(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $title = uniqid('Nouveau projet ', true);

        $this->submitProjectForm($client, '/projets/nouveau', ['title' => $title, 'description' => 'Refonte du portail client.']);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test="project-page-heading"]', $title);
        self::assertSelectorTextContains('[data-test="project-page-description"]', 'Refonte du portail client.');
        self::assertSelectorExists('[data-test="signal-unsplit"]');
    }

    public function testProjectTitleMustBeUniqueIgnoringCase(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $accented = (string) $this->createProject(uniqid('Évolution ', true))->getTitle();

        foreach (['sca', mb_strtolower($accented)] as $title) {
            $this->submitProjectForm($client, '/projets/nouveau', ['title' => $title]);
            self::assertResponseStatusCodeSame(422, $title);
            self::assertSelectorTextContains('[data-test="project-form"]', 'Un projet porte déjà ce titre.');
        }
    }

    public function testLeadRenamesAProject(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $project = $this->createProject();
        $title = uniqid('Renommé ', true);

        $this->submitProjectForm($client, '/projets/' . $project->getId() . '/modifier', ['title' => $title]);

        self::assertResponseRedirects('/projets/' . $project->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test="project-page-heading"]', $title);
    }

    public function testRenamingAProjectToAnExistingTitleIsRefused(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $project = $this->createProject();

        $this->submitProjectForm($client, '/projets/' . $project->getId() . '/modifier', ['title' => 'sca']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="project-form"]', 'Un projet porte déjà ce titre.');
    }

    public function testListIsSortedAlphabeticallyWithAccentsFolded(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');

        $accented = $this->createProject(uniqid('Évolution ', true));

        $crawler = $client->request('GET', '/projets');

        $titles = $crawler->filter('[data-test="project-row"]')->each(static fn ($row): string => (string) $row->attr('data-title'));
        $evolution = array_search($accented->getTitle(), $titles, true);
        $modernisation = array_search('Modernisation technique', $titles, true);
        $support = array_search('Support et maintenance', $titles, true);
        self::assertIsInt($evolution);
        self::assertIsInt($modernisation);
        self::assertIsInt($support);
        self::assertLessThan($modernisation, $evolution);
        self::assertLessThan($support, $modernisation);
    }

    public function testListShowsTotalsAndWhatIsLeftToComplete(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createProjectWithEverySignal();
        $unsplit = $this->createProject();

        $client->request('GET', '/projets');

        $row = \sprintf('[data-title="%s"]', $project->getTitle());
        self::assertSelectorTextContains($row . ' [data-test="estimate"]', '38 j');
        self::assertSelectorExists($row . ' [data-test="estimate-partial"]');
        self::assertSelectorTextContains($row . ' [data-test="signal-to-estimate"]', '1 à estimer');
        self::assertSelectorTextContains($row . ' [data-test="signal-to-assign"]', '1 responsable à désigner');
        self::assertSelectorTextContains($row . ' [data-test="signal-to-reassign"]', '1 responsable à remplacer');
        self::assertSelectorExists(\sprintf('[data-title="%s"] [data-test="signal-unsplit"]', $unsplit->getTitle()));
    }

    public function testMineFilterKeepsProjectsWhereIOwnALeaf(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $mine = $this->createProjectWithEverySignal();
        $unsplit = $this->createProject();

        $client->request('GET', '/projets?mes-responsabilites=1');

        self::assertSelectorExists(\sprintf('[data-title="%s"]', $mine->getTitle()));
        self::assertSelectorNotExists('[data-title="Support et maintenance"]');
        self::assertSelectorNotExists(\sprintf('[data-title="%s"]', $unsplit->getTitle()));
    }

    public function testProjectPageShowsTheTreeAndHighlightsMyLeaves(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $project = $this->createProjectWithEverySignal();
        $crawler = $client->request('GET', '/projets');

        $client->click($crawler->filter(\sprintf('[data-title="%s"] [data-test="project-link"]', $project->getTitle()))->link());

        self::assertSelectorExists('[data-test="lot-row"][data-title="Modèle et règles"][data-mine="true"]');
        self::assertSelectorExists('[data-test="lot-row"][data-title="Écrans"][data-mine="false"]');
        self::assertSelectorTextContains('[data-title="Projets et lots"] [data-test="consumption-estimate"]', '13 j');
        self::assertSelectorExists('[data-title="Saisie des temps"] [data-test="leaf-to-estimate"]');
        self::assertSelectorExists('[data-title="Roadmap"] [data-test="leaf-to-assign"]');
        self::assertSelectorExists('[data-title="Rappels de saisie"] [data-test="leaf-to-reassign"]');
    }

    public function testProjectPageWritesTheOverrunInDaysThenShareOfTheEstimate(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $owner = $this->fixtureUser('olivier.thomas@anyti.me');
        $project = $this->createProject();
        $lot = $this->createLot($project, 2, $owner, title: 'Dépassé');
        foreach (['2026-09-28' => 4, '2026-09-29' => 4, '2026-09-30' => 2] as $day => $quarters) {
            $this->createTimeEntry($owner, $lot, $day, $quarters);
        }

        $client->request('GET', '/projets/' . $project->getId());

        $row = '[data-test="lot-row"][data-title="Dépassé"]';
        self::assertSelectorTextSame($row . ' [data-test="consumption-entered"]', '2,5 j');
        self::assertSelectorTextSame($row . ' [data-test="consumption-overrun"]', '0,5 j (25 %) de dépassement');
    }

    public function testProjectPageQueryCountDoesNotGrowWithItsLots(): void
    {
        $client = $this->clientAs('antoine.leroy@anyti.me');
        $owner = $this->fixtureUser('olivier.thomas@anyti.me');
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
        $client = $this->clientAs('olivier.thomas@anyti.me');
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

    public function testAProjectCarryingTimeCannotBeDeleted(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $project = $this->createProject();
        $split = $this->createLot($project);
        $subLot = $this->createLot($project, 2, null, $split);
        $crawler = $client->request('GET', '/projets/' . $project->getId());
        $deleteForm = $crawler->filter('[data-test="project-delete-confirm"]')->form();
        $this->createTimeEntry($this->createUser(), $subLot, '2026-09-28', 2);

        $client->submit($deleteForm);

        self::assertResponseRedirects('/projets/' . $project->getId());
        $client->followRedirect();
        self::assertSelectorExists('[data-test="project-delete-blocked"]');
        self::assertSelectorNotExists('[data-test="project-delete-confirm"]');
        self::assertSelectorCount(2, '[data-test="lot-delete-blocked"]');
        $this->entityManager()->clear();
        self::assertNotNull($this->entityManager()->find(Project::class, $project->getId()));
    }

    public function testDeletingWithAnInvalidCsrfTokenIsRefused(): void
    {
        $client = $this->clientAs('olivier.thomas@anyti.me');
        $project = $this->createProject();

        $client->request('POST', '/projets/' . $project->getId() . '/supprimer', ['_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Antoine owns « Modèle et règles » and « Saisie des temps », the latter to estimate; « Roadmap » has no owner and
     * « Rappels de saisie » belongs to a former member.
     */
    private function createProjectWithEverySignal(): Project
    {
        $project = $this->createProject();
        $split = $this->createLot($project, title: 'Projets et lots');
        $this->createLot($project, 10, $this->fixtureUser('olivier.thomas@anyti.me'), title: 'Socle et accès');
        $this->createLot($project, 5, $this->fixtureUser('antoine.leroy@anyti.me'), $split, 'Modèle et règles');
        $this->createLot($project, 8, $this->fixtureUser('olivier.thomas@anyti.me'), $split, 'Écrans');
        $this->createLot($project, null, $this->fixtureUser('antoine.leroy@anyti.me'), title: 'Saisie des temps');
        $this->createLot($project, 12, title: 'Roadmap');
        $this->createLot($project, 3, $this->fixtureUser('arthur.petit@anyti.me'), title: 'Rappels de saisie');

        return $project;
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
