<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\TimeEntryRepository;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LotControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTimeEntries;
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

    public function testEstimateCannotGoBelowTheConsumedTimeRoundedUp(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lot = $this->createLotWithTime(5, [4, 4, 4, 1]);
        $url = '/lots/' . $lot->getId() . '/modifier';

        $client->request('GET', $url);
        self::assertSelectorTextContains('[data-test="lot-consumed"]', 'ne peut pas descendre sous 4 j');

        $this->submitLotForm($client, $url, ['estimateDays' => '3']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="lot-form"]', '3,25 j déjà saisis sur cette feuille : l\'estimation ne peut pas descendre sous 4 j.');

        $this->submitLotForm($client, $url, ['estimateDays' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="lot-form"]', 'ne peut plus être retirée');

        $this->submitLotForm($client, $url, ['estimateDays' => '4']);
        self::assertResponseRedirects('/projets/' . $lot->getProject()->getId());
        self::assertSame(4, $this->reloadLot($lot)->getEstimateDays());
        self::assertSame(5, $this->reloadLot($lot)->getInitialEstimateDays());
    }

    public function testALeafOverrunningItsEstimateStaysEditableWithoutRevisingIt(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lot = $this->createLotWithTime(2, [4, 4, 4]);
        $url = '/lots/' . $lot->getId() . '/modifier';

        $this->submitLotForm($client, $url, ['title' => 'Renommée ' . uniqid()]);
        self::assertResponseRedirects('/projets/' . $lot->getProject()->getId());
        self::assertSame(2, $this->reloadLot($lot)->getEstimateDays());

        $this->submitLotForm($client, $url, ['estimateDays' => '1']);
        self::assertResponseStatusCodeSame(422);

        $this->submitLotForm($client, '/lots/' . $lot->getId() . '/sous-lots/nouveau', ['title' => 'Premier']);
        self::assertResponseRedirects('/projets/' . $lot->getProject()->getId());
        self::assertFalse($this->reloadLot($lot)->isLeaf());
    }

    public function testFirstEstimateOfALeafCarryingTimeBecomesItsInitialEstimate(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lot = $this->createLotWithTime(null, [2]);

        $this->submitLotForm($client, '/lots/' . $lot->getId() . '/modifier', ['estimateDays' => '6']);

        self::assertSame(6, $this->reloadLot($lot)->getInitialEstimateDays());
        $client->request('GET', '/projets/' . $lot->getProject()->getId());
        self::assertSelectorTextContains('[data-test="lot-row"][data-title="' . $lot->getTitle() . '"] [data-test="initial-estimate"]', 'initiale 6 j');
    }

    public function testFirstSubLotTakesOverTheTimeOfItsLot(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lot = $this->createLotWithTime(8, [4, 1]);

        $this->submitLotForm($client, '/lots/' . $lot->getId() . '/sous-lots/nouveau', ['title' => 'Modèle']);

        self::assertResponseRedirects('/projets/' . $lot->getProject()->getId());
        $lot = $this->reloadLot($lot);
        $subLot = $lot->getChildren()->first();
        self::assertInstanceOf(Lot::class, $subLot);
        self::assertSame(8, $subLot->getEstimateDays());
        self::assertSame(8, $subLot->getInitialEstimateDays());
        self::assertNull($lot->getInitialEstimateDays());
        self::assertSame(0, $this->timeEntryRepository()->sumQuartersForLotId((int) $lot->getId()));
        self::assertSame(5, $this->timeEntryRepository()->sumQuartersForLotId((int) $subLot->getId()));
    }

    public function testFirstSubLotCannotTakeOverAnEstimateBelowTheConsumedTime(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lot = $this->createLotWithTime(8, [4, 4, 4]);

        $this->submitLotForm($client, '/lots/' . $lot->getId() . '/sous-lots/nouveau', ['title' => 'Modèle', 'estimateDays' => '2']);

        self::assertResponseStatusCodeSame(422);
        self::assertTrue($this->reloadLot($lot)->isLeaf());
    }

    public function testALotCarryingTimeCannotBeDeleted(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lot = $this->createLot($this->createProject(), 3);
        $crawler = $client->request('GET', '/projets/' . $lot->getProject()->getId());
        $deleteForm = $crawler->filter('[data-test="lot-delete-confirm"]')->form();
        $this->createTimeEntry($this->createUser(), $lot, '2026-09-28', 1);

        $client->submit($deleteForm);

        self::assertResponseRedirects('/projets/' . $lot->getProject()->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test="lot-row"][data-title="' . $lot->getTitle() . '"]', $lot->getTitle() ?? '');
        self::assertSelectorExists('[data-test="lot-delete-blocked"]');
        self::assertSelectorNotExists('[data-test="lot-row"][data-title="' . $lot->getTitle() . '"] [data-test="lot-delete-confirm"]');
    }

    public function testLeadPlansALeafWithAStartDateAndATeam(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lead = $this->createUser();
        $prod = $this->createUser();
        $project = $this->createProject();

        $this->postLotForm($client, '/projets/' . $project->getId() . '/lots/nouveau', [
            'title' => 'Socle',
            'estimateDays' => '10',
            'owner' => (string) $lead->getId(),
            'startDate' => '2026-10-05',
            'members' => [['user' => (string) $prod->getId(), 'share' => '50']],
        ]);
        self::assertResponseRedirects('/projets/' . $project->getId());

        $lot = $this->reloadLot($this->onlyLotOf($project));
        self::assertSame('2026-10-05', $lot->getStartDate()?->format('Y-m-d'));
        self::assertSame([[$prod->getId(), 50], [$lead->getId(), 100]], $this->team($lot));

        $crawler = $client->request('GET', '/lots/' . $lot->getId() . '/modifier');
        self::assertSame('2026-10-05', $crawler->filter('[data-test="lot-start-date"]')->attr('value'));
        self::assertCount(2, $crawler->filter('[data-test="lot-member-row"]'));
    }

    public function testRemovingARowRemovesTheMemberButNeverTheOwner(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lead = $this->createUser();
        $lot = $this->createLot($this->createProject(), 5, $lead);
        $this->planLot($lot, new \DateTimeImmutable('2026-10-05'), [[$lead, 50], [$this->createUser(), 50]]);

        $this->postLotForm($client, '/lots/' . $lot->getId() . '/modifier', ['members' => []]);
        self::assertResponseRedirects('/projets/' . $lot->getProject()->getId());

        self::assertSame([[$lead->getId(), 50]], $this->team($this->reloadLot($lot)), 'The owner keeps the share they had.');
    }

    public function testOnlyActivePeopleAreOfferedAsMembersBesidesADeactivatedMemberAlreadyInTheTeam(): void
    {
        $client = $this->clientAs('lead@example.com');
        $former = $this->createUser();
        $member = $this->createUser();
        $lot = $this->planLot($this->createLot($this->createProject(), 5), new \DateTimeImmutable('2026-10-05'), [[$member, 100]]);
        $withFormer = $this->planLot($this->createLot($this->createProject(), 5), new \DateTimeImmutable('2026-10-05'), [[$former, 100]]);
        $former->setActive(false);
        $this->entityManager()->flush();
        $option = \sprintf('[data-test="lot-member-user"] option[value="%d"]', $former->getId());

        $crawler = $client->request('GET', '/lots/' . $lot->getId() . '/modifier');
        self::assertCount(1, $crawler->filter('[data-test="lot-member-row"] ' . \sprintf('[data-test="lot-member-user"] option[value="%d"]', $member->getId())));
        self::assertCount(0, $crawler->filter($option));
        self::assertStringNotContainsString(\sprintf('value=&quot;%d&quot;', $former->getId()), (string) $crawler->filter('[data-controller="form-collection"]')->attr('data-form-collection-prototype-value'));

        $crawler = $client->request('GET', '/lots/' . $withFormer->getId() . '/modifier');
        self::assertStringContainsString('désactivée', $crawler->filter($option)->text());
    }

    public function testAMemberAddedToTheTeamGivesAFullShareByDefault(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lot = $this->createLot($this->createProject(), 5);

        $crawler = $client->request('GET', '/lots/' . $lot->getId() . '/modifier');
        $prototype = (string) $crawler->filter('[data-controller="form-collection"]')->attr('data-form-collection-prototype-value');

        self::assertStringContainsString('<option value="100" selected="selected">100 %</option>', $prototype);
    }

    public function testAPersonAppearsOnlyOnceInATeam(): void
    {
        $client = $this->clientAs('lead@example.com');
        $prod = (string) $this->createUser()->getId();
        $lot = $this->createLot($this->createProject(), 5);

        $this->postLotForm($client, '/lots/' . $lot->getId() . '/modifier', ['members' => [['user' => $prod, 'share' => '50'], ['user' => $prod, 'share' => '25']]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="lot-planning"]', 'Une personne ne figure qu');
        self::assertSame([], $this->team($this->reloadLot($lot)));
    }

    public function testPlanningIsReservedToLeadsOnLeaves(): void
    {
        $client = $this->clientAs('prod@example.com');
        $leaf = $this->createLot($this->createProject(), 4, $this->fixtureUser('prod@example.com'));
        $split = $this->createLot($leaf->getProject());
        $this->createLot($split->getProject(), 2, null, $split);

        $client->request('GET', '/lots/' . $leaf->getId() . '/modifier');
        self::assertSelectorNotExists('[data-test="lot-planning"]');
        $this->postLotForm($client, '/lots/' . $leaf->getId() . '/modifier', ['startDate' => '2026-10-05']);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->reloadLot($leaf)->getStartDate());

        $client->loginUser($this->fixtureUser('lead@example.com'));
        $client->request('GET', '/lots/' . $split->getId() . '/modifier');
        self::assertSelectorNotExists('[data-test="lot-planning"]');
        $client->request('GET', '/lots/' . $leaf->getId() . '/modifier');
        self::assertSelectorExists('[data-test="lot-planning"]');
    }

    public function testFirstSubLotTakesOverTheStartDateAndTheTeamOfItsLot(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lead = $this->createUser();
        $lot = $this->createLot($this->createProject(), 8, $lead);
        $this->planLot($lot, new \DateTimeImmutable('2026-10-05'), [[$lead, 75]]);

        $crawler = $client->request('GET', '/lots/' . $lot->getId() . '/sous-lots/nouveau');
        self::assertSame('2026-10-05', $crawler->filter('[data-test="lot-start-date"]')->attr('value'));
        self::assertCount(1, $crawler->filter('[data-test="lot-member-row"]'));

        $client->submit($crawler->filter('[data-test="lot-form"]')->form(['lot[title]' => 'Modèle']));
        self::assertResponseRedirects('/projets/' . $lot->getProject()->getId());

        $lot = $this->reloadLot($lot);
        self::assertNull($lot->getStartDate());
        self::assertSame([], $this->team($lot));
        $subLot = $lot->getChildren()->first();
        self::assertInstanceOf(Lot::class, $subLot);
        self::assertSame('2026-10-05', $subLot->getStartDate()?->format('Y-m-d'));
        self::assertSame([[$lead->getId(), 75]], $this->team($subLot));
    }

    public function testAddingAMemberWhoWouldBeOverloadedIsRefused(): void
    {
        $client = $this->plannerClient();
        $alice = $this->createUser();
        $busy = $this->busyLeaf($alice, '2026-10-05');
        $lot = $this->createLot($this->createProject(), 10);

        $this->postLotForm($client, '/lots/' . $lot->getId() . '/modifier', ['startDate' => '2026-10-12', 'members' => [['user' => (string) $alice->getId(), 'share' => '100']]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="lot-planning"]', 'atteindrait 200 % le 12/10/2026, avec « ' . $busy->getTitle() . ' »');
        self::assertSame([], $this->team($this->reloadLot($lot)));
    }

    public function testMovingTheStartDateOrDesignatingAnOwnerIntoAnOverloadIsRefused(): void
    {
        $client = $this->plannerClient();
        $alice = $this->createUser();
        $this->busyLeaf($alice, '2026-10-05');
        $planned = $this->planLot($this->createLot($this->createProject(), 10), new \DateTimeImmutable('2026-10-19'), [[$alice, 25]]);
        $unplanned = $this->planLot($this->createLot($this->createProject(), 10), new \DateTimeImmutable('2026-10-12'), []);

        $this->postLotForm($client, '/lots/' . $planned->getId() . '/modifier', ['startDate' => '2026-10-12']);
        self::assertResponseStatusCodeSame(422);

        $this->postLotForm($client, '/lots/' . $unplanned->getId() . '/modifier', ['owner' => (string) $alice->getId()]);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->reloadLot($unplanned)->getOwner());
    }

    public function testRevisingTheEstimateAloneIsNeverRefused(): void
    {
        $client = $this->plannerClient();
        $alice = $this->createUser();
        $this->busyLeaf($alice, '2026-10-05');
        $overloaded = $this->planLot($this->createLot($this->createProject(), 10), new \DateTimeImmutable('2026-10-12'), [[$alice, 50]]);

        $this->postLotForm($client, '/lots/' . $overloaded->getId() . '/modifier', ['estimateDays' => '30']);

        self::assertResponseRedirects();
        self::assertSame(30, $this->reloadLot($overloaded)->getEstimateDays());
    }

    public function testReducingAnExistingOverloadIsAccepted(): void
    {
        $client = $this->plannerClient();
        $alice = $this->createUser();
        $this->busyLeaf($alice, '2026-10-05');
        $overloaded = $this->planLot($this->createLot($this->createProject(), 10), new \DateTimeImmutable('2026-10-12'), [[$alice, 75]]);

        $this->postLotForm($client, '/lots/' . $overloaded->getId() . '/modifier', ['members' => [['user' => (string) $alice->getId(), 'share' => '50']]]);

        self::assertResponseRedirects();
        self::assertSame([[$alice->getId(), 50]], $this->team($this->reloadLot($overloaded)));
    }

    public function testFirstSubLotTakingOverAnOverloadedPlanningIsAccepted(): void
    {
        $client = $this->plannerClient();
        $alice = $this->createUser();
        $this->busyLeaf($alice, '2026-10-05');
        $lot = $this->planLot($this->createLot($this->createProject(), 10), new \DateTimeImmutable('2026-10-12'), [[$alice, 100]]);

        $crawler = $client->request('GET', '/lots/' . $lot->getId() . '/sous-lots/nouveau');
        $client->submit($crawler->filter('[data-test="lot-form"]')->form(['lot[title]' => 'Modèle']));

        self::assertResponseRedirects();
        self::assertCount(1, $this->reloadLot($lot)->getChildren());
    }

    /**
     * @param positive-int|null $estimateDays
     * @param list<int<1, 4>>   $quarters     one entry per day, from Monday 2026-09-21
     */
    private function createLotWithTime(?int $estimateDays, array $quarters): Lot
    {
        $lot = $this->createLot($this->createProject(), $estimateDays);
        $lot->setInitialEstimateDays($estimateDays);
        $user = $this->createUser();
        foreach ($quarters as $offset => $quarter) {
            $this->createTimeEntry($user, $lot, \sprintf('2026-09-%d', 21 + $offset), $quarter);
        }

        return $lot;
    }

    private function timeEntryRepository(): TimeEntryRepository
    {
        $repository = self::getContainer()->get(TimeEntryRepository::class);
        \assert($repository instanceof TimeEntryRepository);

        return $repository;
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

    /**
     * Posts the form as the browser would, with values the crawler cannot set, such as added team rows.
     *
     * @param array<string, mixed> $values
     */
    private function postLotForm(KernelBrowser $client, string $url, array $values): void
    {
        $form = $client->request('GET', $url)->filter('[data-test="lot-form"]')->form();
        $payload = $form->getPhpValues();
        \assert(\is_array($payload['lot']));
        if (\array_key_exists('members', $values)) {
            unset($payload['lot']['members']);
        }

        $client->request($form->getMethod(), $form->getUri(), ['lot' => array_replace($payload['lot'], $values)]);
    }

    /**
     * @return list<array{int|null, int}>
     */
    private function team(Lot $lot): array
    {
        return array_map(static fn (LotMember $member): array => [$member->getUser()->getId(), $member->getShare()], $lot->getMembers()->getValues());
    }

    private function onlyLotOf(Project $project): Lot
    {
        $reloaded = $this->entityManager()->find(Project::class, $project->getId());
        self::assertInstanceOf(Project::class, $reloaded);
        $lot = $reloaded->getLots()->first();
        self::assertInstanceOf(Lot::class, $lot);

        return $lot;
    }

    /**
     * A lead signed in on Friday 2026-10-02, the day before the planning scenarios start.
     */
    private function plannerClient(): KernelBrowser
    {
        self::mockTime('2026-10-02 10:00');

        return $this->clientAs('lead@example.com');
    }

    /**
     * A leaf keeping the person fully busy for two weeks from the given Monday.
     */
    private function busyLeaf(User $user, string $monday): Lot
    {
        return $this->planLot($this->createLot($this->createProject(), 10, title: uniqid('Occupée ', true)), new \DateTimeImmutable($monday), [[$user, 100]]);
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
