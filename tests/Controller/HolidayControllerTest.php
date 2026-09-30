<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\Type\HolidayCalendar;
use App\Repository\UserRepository;
use App\Service\HolidayManager;
use App\Tests\Support\CreatesUsers;
use App\Tests\Support\PurgesHolidayAdjustments;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;

final class HolidayControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesUsers;
    use PurgesHolidayAdjustments;

    protected function setUp(): void
    {
        self::mockTime('2026-09-30 10:00');
    }

    #[DataProvider('nonDirectorProvider')]
    public function testHolidayPagesAreForbiddenToNonDirectors(string $email): void
    {
        $client = self::createClient();
        $client->loginUser($this->fixtureUser($email));

        foreach (['/jours-feries', '/jours-feries/2031'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
        $client->request('POST', '/jours-feries/retirer', ['calendar' => 'fr', 'day' => '2031-07-14']);
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/saisie');
        self::assertSelectorNotExists('[data-test="nav-holidays"]');
    }

    /**
     * @return \Generator<array{string}>
     */
    public static function nonDirectorProvider(): \Generator
    {
        yield 'lead' => ['lead@example.com'];
        yield 'prod' => ['prod@example.com'];
    }

    public function testTheCurrentYearListsTheLegalHolidaysOfBothCalendarsWeekendsIncluded(): void
    {
        $client = $this->directorClient();

        $crawler = $client->request('GET', '/jours-feries');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="nav-holidays"]');
        self::assertSame('2026', $crawler->filter('[data-test="holiday-year"]')->attr('data-year'));
        self::assertSame('fr', $crawler->filter('[data-test^="holiday-calendar-"]:checked')->attr('value'));
        self::assertCount(11, $this->lines($crawler, 'fr'));
        self::assertCount(10, $this->lines($crawler, 'be'));
        self::assertSame(['2026-08-15', '2026-11-01'], $this->lines($crawler, 'fr')->filter('[data-weekend="true"]')->each(static fn (Crawler $line): string => (string) $line->attr('data-day')));
        self::assertCount(0, $this->lines($crawler, 'fr')->filter('[data-weekend="true"] [data-test="holiday-remove"]'));
        self::assertSame('Fête nationale', $this->line($crawler, 'be', '2026-07-21')->filter('[data-test="holiday-label"]')->text());
    }

    public function testYearsOutsideFourDigitsAreNotFound(): void
    {
        $client = $this->directorClient();

        $client->request('GET', '/jours-feries/0000');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/jours-feries/1000');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="year-prev"]');

        $client->request('GET', '/jours-feries/9999');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="year-next"]');
    }

    public function testTheDirectionAddsAHolidayToOneCalendarThenCancelsIt(): void
    {
        $client = $this->directorClient();

        $this->submitAddition($client, ['calendar' => 'be', 'day' => '2031-06-11', 'label' => 'Remplacement du 15 août']);

        self::assertResponseRedirects('/jours-feries/2031');
        $crawler = $client->followRedirect();
        $added = $this->line($crawler, 'be', '2031-06-11');
        self::assertSame('added', $added->attr('data-status'));
        self::assertSame('Remplacement du 15 août', $added->filter('[data-test="holiday-label"]')->text());
        self::assertCount(0, $this->lines($crawler, 'fr')->filter('[data-day="2031-06-11"]'));

        $client->submit($added->filter('[data-test="holiday-cancel"]')->form());

        self::assertResponseRedirects('/jours-feries/2031');
        self::assertCount(0, $this->lines($client->followRedirect(), 'be')->filter('[data-day="2031-06-11"]'));
    }

    #[DataProvider('refusedAdditionProvider')]
    public function testRefusedAdditions(string $day, string $label, bool $removeFirst, string $message): void
    {
        $client = $this->directorClient();
        if ($removeFirst) {
            $this->holidayManager()->remove(HolidayCalendar::France, new \DateTimeImmutable($day));
        }

        $this->submitAddition($client, ['calendar' => 'fr', 'day' => $day, 'label' => $label]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="holiday-form"]', $message);
    }

    /**
     * @return \Generator<array{string, string, bool, string}>
     */
    public static function refusedAdditionProvider(): \Generator
    {
        yield 'saturday' => ['2031-06-14', 'Pont', false, 'que du lundi au vendredi'];
        yield 'legal holiday' => ['2031-07-14', 'Fête', false, 'Le 14/07/2031 est déjà férié dans le calendrier France.'];
        yield 'no label' => ['2031-06-11', '', false, 'Donnez un libellé au jour férié'];
        yield 'removed legal holiday' => ['2031-06-02', 'Pentecôte', true, 'annulez le retrait pour le rétablir'];
    }

    public function testTheDirectionRemovesALegalHolidayThenCancelsTheRemoval(): void
    {
        $client = $this->directorClient();
        $crawler = $client->request('GET', '/jours-feries/2031');

        $client->submit($this->line($crawler, 'fr', '2031-06-02')->filter('[data-test="holiday-remove"]')->form());

        self::assertResponseRedirects('/jours-feries/2031');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Le 02/06/2031 est retiré du calendrier France');
        $removed = $this->line($crawler, 'fr', '2031-06-02');
        self::assertSame('removed', $removed->attr('data-status'));
        self::assertSame('legal', $this->line($crawler, 'be', '2031-06-02')->attr('data-status'));

        $client->submit($removed->filter('[data-test="holiday-cancel"]')->form());

        self::assertSame('legal', $this->line($client->followRedirect(), 'fr', '2031-06-02')->attr('data-status'));
    }

    public function testRemovingADayThatIsNotALegalHolidayShowsTheRefusal(): void
    {
        $client = $this->directorClient();
        $crawler = $client->request('GET', '/jours-feries/2031');
        $form = $this->line($crawler, 'fr', '2031-06-02')->filter('[data-test="holiday-remove"]')->form();
        $form->setValues(['day' => '2031-06-03']);

        $client->submit($form);

        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Le 03/06/2031 n\'est pas un jour férié légal du calendrier France');
    }

    public function testAnInvalidCsrfTokenIsForbidden(): void
    {
        $client = $this->directorClient();

        $client->request('POST', '/jours-feries/retirer', ['_token' => 'invalide', 'calendar' => 'fr', 'day' => '2031-07-14']);

        self::assertResponseStatusCodeSame(403);
    }

    private function directorClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser($this->fixtureUser('admin@example.com'));

        return $client;
    }

    /**
     * @param array<string, string> $values
     */
    private function submitAddition(KernelBrowser $client, array $values): void
    {
        $crawler = $client->request('GET', '/jours-feries/' . substr($values['day'], 0, 4));
        $fields = [];
        foreach ($values as $field => $value) {
            $fields['holiday_addition[' . $field . ']'] = $value;
        }

        $client->submit($crawler->filter('[data-test="holiday-form"]')->form($fields));
    }

    private function lines(Crawler $crawler, string $calendar): Crawler
    {
        return $crawler->filter(\sprintf('[data-test="holiday-calendar"][data-calendar="%s"] [data-test="holiday-line"]', $calendar));
    }

    private function line(Crawler $crawler, string $calendar, string $day): Crawler
    {
        return $this->lines($crawler, $calendar)->filter(\sprintf('[data-day="%s"]', $day));
    }

    private function holidayManager(): HolidayManager
    {
        $manager = self::getContainer()->get(HolidayManager::class);
        \assert($manager instanceof HolidayManager);

        return $manager;
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
