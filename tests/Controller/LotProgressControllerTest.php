<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Lot;
use App\Entity\LotProgress;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\LotProgressRepository;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesProgress;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LotProgressControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesProgress;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    public function testLeadDeclaresTheProgressOfALeafFromThePageOfItsProject(): void
    {
        $client = $this->clientAs('lead@example.com');
        self::mockTime('2026-10-02 10:00');
        $project = $this->createProject();
        $lot = $this->leafWithTime($project, 'Synchronisation', 20, 10);

        $crawler = $client->request('GET', '/projets/' . $project->getId());
        $client->submit($crawler->filter('[data-test="lot-row"][data-title="Synchronisation"] [data-test="progress-form"]')->form(['percent' => '40']));

        self::assertResponseRedirects('/projets/' . $project->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Avancement de « Synchronisation » : 40 %.');
        self::assertSelectorExists('[data-test="lot-row"][data-title="Synchronisation"] [data-test="progress-select"] option[value="40"][selected]');
        self::assertSelectorTextContains('[data-test="lot-row"][data-title="Synchronisation"] [data-test="progress-declared-on"]', 'déclaré le 02/10');
        $declaration = $this->onlyDeclarationOf($lot);
        self::assertSame([40, 40, 60], [$declaration->getPercent(), $declaration->getEnteredQuarters(), $declaration->getRemainingQuarters()]);
        self::assertSame('lead@example.com', $declaration->getAuthor()->getEmail());
    }

    public function testDirectionDeclaresTheProgressOfAnyEstimatedLeaf(): void
    {
        $client = $this->clientAs('admin@example.com');
        $project = $this->createProject();
        $lot = $this->createLot($project, 10, $this->fixtureUser('lead@example.com'), title: 'Écrans');

        $this->postProgress($client, $project, 'Écrans', '30');

        self::assertResponseRedirects('/projets/' . $project->getId());
        self::assertSame(7 * 4, $this->onlyDeclarationOf($lot)->getRemainingQuarters());
    }

    public function testOwnerDeclaresTheProgressOfTheirLeafButReadsTheOthers(): void
    {
        $client = $this->clientAs('prod@example.com');
        $project = $this->createProject();
        $this->createLot($project, 10, $this->fixtureUser('prod@example.com'), title: 'Mien');
        $other = $this->createLot($project, 10, $this->fixtureUser('lead@example.com'), title: 'Autre');
        $this->createProgress($other, $this->fixtureUser('lead@example.com'), '2026-09-28', 40, 0);

        $client->request('GET', '/projets/' . $project->getId());

        self::assertSelectorExists('[data-test="lot-row"][data-title="Mien"] [data-test="progress-form"]');
        self::assertSelectorNotExists('[data-test="lot-row"][data-title="Autre"] [data-test="progress-form"]');
        self::assertSelectorTextContains('[data-test="lot-row"][data-title="Autre"] [data-test="progress"]', '40 %');
        self::assertSelectorTextContains('[data-test="lot-row"][data-title="Autre"] [data-test="progress-declared-on"]', 'au 28/09');

        $this->postProgress($client, $project, 'Mien', '50');
        self::assertResponseRedirects('/projets/' . $project->getId());

        $client->request('POST', '/lots/' . $other->getId() . '/avancement', ['percent' => '50']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testNoProgressOnASplitLotNorOnALeafToEstimate(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $split = $this->createLot($project, title: 'Découpé');
        $this->createLot($project, 3, parent: $split);
        $toEstimate = $this->createLot($project, title: 'À estimer');

        $client->request('GET', '/projets/' . $project->getId());
        self::assertSelectorNotExists('[data-test="lot-row"][data-title="Découpé"] [data-test="progress-form"]');
        self::assertSelectorTextContains('[data-test="lot-row"][data-title="À estimer"] [data-test="progress-none"]', '—');

        foreach ([$split, $toEstimate] as $lot) {
            $client->request('POST', '/lots/' . $lot->getId() . '/avancement', ['percent' => '50']);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testAnInvalidTokenIsRefused(): void
    {
        $client = $this->clientAs('lead@example.com');
        $lot = $this->createLot($this->createProject(), 10);

        $client->request('POST', '/lots/' . $lot->getId() . '/avancement', ['percent' => '50', '_token' => 'invalide']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->declarationsOf($lot));
    }

    public function testAPercentOffTheStepsIsRefusedWithAMessage(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $lot = $this->createLot($project, 10, title: 'Synchronisation');

        $this->postProgress($client, $project, 'Synchronisation', '37');

        self::assertResponseRedirects('/projets/' . $project->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Un avancement se déclare de 0 à 100 %, par pas de 5 %.');
        self::assertSame([], $this->declarationsOf($lot));
    }

    public function testALeafWithoutTimeCannotBeDeclaredCompleteAtAHundredPercent(): void
    {
        $client = $this->clientAs('lead@example.com');
        $project = $this->createProject();
        $lot = $this->createLot($project, 10, title: 'Synchronisation');

        $this->postProgress($client, $project, 'Synchronisation', '100');

        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Aucun temps n\'est saisi sur « Synchronisation » : son avancement ne peut pas être de 100 %.');
        self::assertSame([], $this->declarationsOf($lot));
    }

    /**
     * Posts the form of the leaf with a value the select may not offer.
     */
    private function postProgress(KernelBrowser $client, Project $project, string $title, string $percent): void
    {
        $form = $client->request('GET', '/projets/' . $project->getId())
            ->filter('[data-test="lot-row"][data-title="' . $title . '"] [data-test="progress-form"]')
            ->form();

        $client->request('POST', $form->getUri(), ['_token' => $form->getValues()['_token'], 'percent' => $percent]);
    }

    /**
     * @param non-empty-string $title
     * @param positive-int     $estimateDays
     * @param positive-int     $enteredDays
     */
    private function leafWithTime(Project $project, string $title, int $estimateDays, int $enteredDays): Lot
    {
        $lot = $this->createLot($project, $estimateDays, title: $title);
        $user = $this->createUser();
        $day = new \DateTimeImmutable('2026-09-01');
        for ($i = 0; $i < $enteredDays; ++$i, $day = $day->modify('+1 weekday')) {
            $this->createTimeEntry($user, $lot, $day->format('Y-m-d'), 4);
        }

        return $lot;
    }

    private function onlyDeclarationOf(Lot $lot): LotProgress
    {
        $declarations = $this->declarationsOf($lot);
        self::assertCount(1, $declarations);

        return $declarations[0];
    }

    /**
     * @return list<LotProgress>
     */
    private function declarationsOf(Lot $lot): array
    {
        $repository = self::getContainer()->get(LotProgressRepository::class);
        \assert($repository instanceof LotProgressRepository);

        return $repository->findBy(['lot' => $lot]);
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
