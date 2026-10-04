<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Support\CreatesFavorites;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpKernel\Profiler\Profile;

final class TimesheetControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesFavorites;
    use CreatesProjects;
    use CreatesTimeEntries;
    use CreatesUsers;

    protected function setUp(): void
    {
        self::mockTime('2026-09-30 10:00');
    }

    public function testLoginLeadsToTheCurrentWeek(): void
    {
        $client = self::createClient();
        $user = $this->createUser();
        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Se connecter')->form(['_username' => (string) $user->getEmail(), '_password' => 'password']));

        self::assertResponseRedirects('/saisie');
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test="week-label"]', 'Semaine du 28/09 au 02/10/2026');
        self::assertSelectorExists('[data-test="nav-timesheet"]');
        self::assertSelectorExists('[data-test="week-current"][aria-current="page"]');
    }

    public function testNavigatesFromWeekToWeek(): void
    {
        $client = $this->signedInClient();

        $client->request('GET', '/saisie/2027-W01');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test="week-label"]', 'Semaine du 04/01 au 08/01/2027');
        self::assertSelectorExists('[data-test="week-prev"][href="/saisie/2026-W53"]');
        self::assertSelectorExists('[data-test="week-next"][href="/saisie/2027-W02"]');
        self::assertSelectorExists('[data-test="week-current"][href="/saisie"][aria-current="false"]');
    }

    public function testAnInvalidWeekIsNotFound(): void
    {
        $client = $this->signedInClient();

        $client->request('GET', '/saisie/2027-W53');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheTimesheetRequiresToBeSignedIn(): void
    {
        $client = self::createClient();

        $client->request('GET', '/saisie');

        self::assertResponseRedirects('/login');
    }

    public function testTheDashboardOffersTheTimesheet(): void
    {
        $client = $this->signedInClient();

        $client->request('GET', '/');

        self::assertSelectorExists('[data-test="home-timesheet"]');
    }

    public function testQueryCountDoesNotGrowWithTheRowsOrTheFavorites(): void
    {
        $client = self::createClient();
        $small = $this->createUser();
        $large = $this->createUser();
        $project = $this->createProject();
        $this->createTimeEntry($small, $this->createLot($project), '2026-09-28', 1);
        foreach (['2026-09-28', '2026-09-29', '2026-09-22'] as $day) {
            $split = $this->createLot($this->createProject());
            $this->createTimeEntry($large, $this->createLot($split->getProject(), parent: $split), $day, 1);
            $this->createTimeEntry($large, $this->createLot($project), $day, 1);
        }
        $this->createTimeEntry($large, $favorite = $this->createLot($project), '2026-09-30', 1);
        $this->createFavorite($large, $favorite);
        $split = $this->createLot($this->createProject());
        $this->createFavorite($large, $this->createLot($split->getProject(), parent: $split));
        $client->request('GET', '/login');

        $smallCount = $this->queryCount($client, $small);

        self::assertLessThanOrEqual(6, $smallCount);
        self::assertSame($smallCount, $this->queryCount($client, $large));
    }

    private function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser($this->createUser());

        return $client;
    }

    private function queryCount(KernelBrowser $client, User $user): int
    {
        $client->loginUser($user);
        $client->enableProfiler();
        $client->request('GET', '/saisie/2026-W40');
        self::assertResponseIsSuccessful();

        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }
}
