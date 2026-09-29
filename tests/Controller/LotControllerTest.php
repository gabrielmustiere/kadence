<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LotControllerTest extends WebTestCase
{
    use CreatesProjects;
    use CreatesUsers;

    public function testLeadChainsLotsWithAddAnother(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $url = '/projets/' . $project->getId() . '/lots/nouveau';

        $this->submitLotForm($client, $url, ['title' => 'Socle', 'estimateDays' => '5'], 'lot-submit-add-another');
        self::assertResponseRedirects($url);
        $crawler = $client->followRedirect();
        self::assertSame('', $crawler->filter('[data-test="lot-title"]')->attr('value') ?? '');

        $this->submitLotForm($client, $url, ['title' => 'Écrans']);
        self::assertResponseRedirects('/projets/' . $project->getId());
        $client->followRedirect();
        self::assertSelectorExists('[data-test="lot-row"][data-title="Socle"]');
        self::assertSelectorExists('[data-test="lot-row"][data-title="Écrans"] [data-test="leaf-to-estimate"]');
        self::assertSelectorExists('[data-test="lot-row"][data-title="Écrans"] [data-test="leaf-to-assign"]');
    }

    #[DataProvider('invalidEstimateProvider')]
    public function testEstimateMustBeAPositiveWholeNumberOfDays(string $estimate): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();

        $this->submitLotForm($client, '/projets/' . $project->getId() . '/lots/nouveau', ['title' => 'Socle', 'estimateDays' => $estimate]);

        self::assertResponseStatusCodeSame(422);
        $reloaded = $this->entityManager()->find(Project::class, $project->getId());
        self::assertInstanceOf(Project::class, $reloaded);
        self::assertCount(0, $reloaded->getLots());
    }

    /**
     * @return \Generator<array{string}>
     */
    public static function invalidEstimateProvider(): \Generator
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'decimal' => ['2.5'];
    }

    public function testFirstSubLotTakesOverItsLotEstimateAndOwner(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lead = $this->fixtureUser('lead@example.com');
        $lot = $this->createLot($this->createProject(), 8, $lead);

        $crawler = $client->request('GET', '/lots/' . $lot->getId() . '/sous-lots/nouveau');
        self::assertSelectorExists('[data-test="lot-takes-over"]');
        self::assertSame('8', $crawler->filter('[data-test="lot-estimate"]')->attr('value'));
        self::assertSame((string) $lead->getId(), $crawler->filter('[data-test="lot-owner"] option[selected]')->attr('value'));

        $client->submit($crawler->filter('[data-test="lot-form"]')->form(['lot[title]' => 'Modèle']));
        self::assertResponseRedirects('/projets/' . $lot->getProject()->getId());

        $lot = $this->reloadLot($lot);
        self::assertNull($lot->getEstimateDays());
        self::assertNull($lot->getOwner());
        $subLot = $lot->getChildren()->first();
        self::assertInstanceOf(Lot::class, $subLot);
        self::assertSame(8, $subLot->getEstimateDays());
        self::assertSame($lead->getId(), $subLot->getOwner()?->getId());

        $client->request('GET', '/lots/' . $lot->getId() . '/sous-lots/nouveau');
        self::assertSelectorNotExists('[data-test="lot-takes-over"]');
    }

    public function testSplitLotOffersNeitherEstimateNorOwner(): void
    {
        $client = $this->clientAs('lead@example.com');
        $split = $this->createLot($this->createProject());
        $this->createLot($split->getProject(), 2, null, $split);

        $client->request('GET', '/lots/' . $split->getId() . '/modifier');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="lot-title"]');
        self::assertSelectorNotExists('[data-test="lot-estimate"]');
        self::assertSelectorNotExists('[data-test="lot-owner"]');
    }

    public function testRenamingALotToASiblingTitleIsRefused(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $this->createLot($project, null, null, null, 'Front');
        $back = $this->createLot($project, null, null, null, 'Back');

        $this->submitLotForm($client, '/lots/' . $back->getId() . '/modifier', ['title' => 'front']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="lot-form"]', 'Un lot de ce projet porte déjà ce titre.');
        self::assertSame('Back', $this->reloadLot($back)->getTitle());
    }

    public function testSubLotCannotBeSplit(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lot = $this->createLot($this->createProject());
        $subLot = $this->createLot($lot->getProject(), 2, null, $lot);

        $client->request('GET', '/lots/' . $subLot->getId() . '/sous-lots/nouveau');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnerEditsTheirLeafWithoutChoosingTheOwner(): void
    {
        $client = $this->clientAs('prod@example.com');
        $prod = $this->fixtureUser('prod@example.com');
        $leaf = $this->createLot($this->createProject(), 4, $prod);

        $crawler = $client->request('GET', '/lots/' . $leaf->getId() . '/modifier');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="lot-estimate"]');
        self::assertSelectorNotExists('[data-test="lot-owner"]');

        $client->submit($crawler->filter('[data-test="lot-form"]')->form(['lot[estimateDays]' => '6']));
        self::assertResponseRedirects('/projets/' . $leaf->getProject()->getId());

        $leaf = $this->reloadLot($leaf);
        self::assertSame(6, $leaf->getEstimateDays());
        self::assertSame($prod->getId(), $leaf->getOwner()?->getId());
    }

    public function testProdCannotEditOtherLotsNorManageTheStructure(): void
    {
        $client = $this->clientAs('prod@example.com');
        $project = $this->createProject();
        $othersLeaf = $this->createLot($project, 4, $this->fixtureUser('lead@example.com'));
        $split = $this->createLot($project);
        $this->createLot($project, 2, $this->fixtureUser('prod@example.com'), $split);

        foreach (['/lots/' . $othersLeaf->getId() . '/modifier', '/lots/' . $split->getId() . '/modifier', '/projets/' . $project->getId() . '/lots/nouveau', '/lots/' . $split->getId() . '/sous-lots/nouveau'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }

        $client->request('POST', '/lots/' . $othersLeaf->getId() . '/supprimer');
        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerChoicesListActivePeopleAndKeepADeactivatedCurrentOwner(): void
    {
        $client = $this->clientAs('lead@example.com');
        $former = $this->fixtureUser('ancien@example.com');
        $project = $this->createProject();
        $orphan = $this->createLot($project, 3, $former);
        $assigned = $this->createLot($project, 3, $this->fixtureUser('lead@example.com'));

        $crawler = $client->request('GET', '/lots/' . $assigned->getId() . '/modifier');
        self::assertStringNotContainsString('Arthur Petit', $crawler->filter('[data-test="lot-owner"]')->text());

        $crawler = $client->request('GET', '/lots/' . $orphan->getId() . '/modifier');
        self::assertStringContainsString('Arthur Petit — désactivée', $crawler->filter('[data-test="lot-owner"] option[selected]')->text());

        $client->submit($crawler->filter('[data-test="lot-form"]')->form(['lot[title]' => 'Renommé']));
        self::assertResponseRedirects();
        self::assertSame($former->getId(), $this->reloadLot($orphan)->getOwner()?->getId());
    }

    public function testTitlesAreUniqueAmongSiblingsOnly(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $front = $this->createLot($project, null, null, null, 'Front');
        $back = $this->createLot($project, null, null, null, 'Back');
        $this->createLot($project, 2, null, $front, 'Tests');

        $this->submitLotForm($client, '/projets/' . $project->getId() . '/lots/nouveau', ['title' => 'FRONT']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="lot-form"]', 'Un lot de ce projet porte déjà ce titre.');

        $this->submitLotForm($client, '/lots/' . $front->getId() . '/sous-lots/nouveau', ['title' => 'tests']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="lot-form"]', 'Un sous-lot de ce lot porte déjà ce titre.');

        $this->submitLotForm($client, '/lots/' . $back->getId() . '/sous-lots/nouveau', ['title' => 'Tests']);
        self::assertResponseRedirects('/projets/' . $project->getId());
    }

    public function testDeletingTheLastSubLotMovesItsEstimateAndOwnerBackToItsLot(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lead = $this->fixtureUser('lead@example.com');
        $lot = $this->createLot($this->createProject(), null, null, null, 'Lot découpé');
        $this->createLot($lot->getProject(), 3, $lead, $lot, 'Dernier sous-lot');

        $crawler = $client->request('GET', '/projets/' . $lot->getProject()->getId());
        $row = '[data-test="lot-row"][data-title="Dernier sous-lot"]';
        self::assertSelectorTextContains($row . ' [data-test="lot-delete-scope"]', 'remonteront sur « Lot découpé »');
        $client->submit($crawler->filter($row . ' [data-test="lot-delete-confirm"]')->form());
        self::assertResponseRedirects('/projets/' . $lot->getProject()->getId());

        $lot = $this->reloadLot($lot);
        self::assertTrue($lot->isLeaf());
        self::assertSame(3, $lot->getEstimateDays());
        self::assertSame($lead->getId(), $lot->getOwner()?->getId());
    }

    /**
     * @param array<string, string> $values
     */
    private function submitLotForm(KernelBrowser $client, string $url, array $values, string $button = 'lot-submit'): void
    {
        $crawler = $client->request('GET', $url);
        $fields = [];
        foreach ($values as $field => $value) {
            $fields['lot[' . $field . ']'] = $value;
        }

        $client->submit($crawler->filter('[data-test="' . $button . '"]')->form($fields));
    }

    private function reloadLot(Lot $lot): Lot
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();
        $reloaded = $entityManager->find(Lot::class, $lot->getId());
        self::assertInstanceOf(Lot::class, $reloaded);

        return $reloaded;
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
