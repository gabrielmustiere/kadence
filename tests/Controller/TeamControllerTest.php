<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\Role;
use App\Repository\UserRepository;
use App\Repository\WeeklyMaxRepository;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TeamControllerTest extends WebTestCase
{
    use CreatesTimeEntries;
    use CreatesUsers;

    #[DataProvider('nonDirectorProvider')]
    public function testTeamManagementIsForbiddenToNonDirectors(string $email): void
    {
        $client = self::createClient();
        $client->loginUser($this->fixtureUser($email));
        $member = $this->createUser();

        foreach (['/equipe', '/equipe/nouveau', '/equipe/' . $member->getId() . '/modifier'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }

        $client->request('POST', '/equipe/' . $member->getId() . '/desactiver');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return \Generator<array{string}>
     */
    public static function nonDirectorProvider(): \Generator
    {
        yield 'lead' => ['lead@example.com'];
        yield 'prod' => ['prod@example.com'];
    }

    public function testTeamEntryIsShownInNavigationToDirectionOnly(): void
    {
        $client = $this->directorClient();
        $client->request('GET', '/');
        self::assertSelectorExists('[data-test="nav-team"]');
        self::assertSelectorExists('[data-test="home-team"]');

        $client->loginUser($this->fixtureUser('prod@example.com'));
        $client->request('GET', '/');
        self::assertSelectorNotExists('[data-test="nav-team"]');
        self::assertSelectorNotExists('[data-test="home-team"]');
        self::assertSelectorExists('[data-test="home-account"]');
        self::assertSelectorTextContains('[data-test="user-menu-name"]', 'Paula Durand');
    }

    public function testListShowsIdentityRoleAndStatusOnly(): void
    {
        $client = $this->directorClient();

        $client->request('GET', '/equipe');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-email="lead@example.com"] [data-test="member-role"]', 'Lead');
        self::assertSelectorTextContains('[data-email="ancien@example.com"] [data-test="member-status"]', 'Désactivée');
        self::assertSelectorTextContains('[data-email="prod@example.com"] [data-test="member-status"]', 'Active');
    }

    public function testRegisterShowsTemporaryPasswordOnlyOnce(): void
    {
        $client = $this->directorClient();
        $email = uniqid('new-', true) . '@example.com';

        $this->submitMemberForm($client, '/equipe/nouveau', ['firstName' => 'Jeanne', 'lastName' => 'Dupont', 'email' => $email, 'role' => 'lead']);

        $user = $this->userRepository()->findOneByEmail($email);
        self::assertNotNull($user);
        self::assertResponseRedirects('/equipe/' . $user->getId() . '/mot-de-passe-provisoire');

        $crawler = $client->followRedirect();
        $temporaryPassword = $crawler->filter('[data-test="temporary-password"]')->text();
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($this->passwordHasher()->isPasswordValid($user, $temporaryPassword));
        self::assertSame(Role::Lead, $user->getRole());
        self::assertSame(HolidayCalendar::France, $user->getHolidayCalendar());
        self::assertTrue($user->mustChangePassword());

        $client->request('GET', '/equipe/' . $user->getId() . '/mot-de-passe-provisoire');
        self::assertResponseRedirects('/equipe');
    }

    public function testRegisterRefusesEmailOfActiveMemberWhateverTheCase(): void
    {
        $client = $this->directorClient();

        $this->submitMemberForm($client, '/equipe/nouveau', ['firstName' => 'Louis', 'lastName' => 'Bis', 'email' => 'LEAD@example.com', 'role' => 'prod']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="team-member-form"]', 'déjà utilisé');
    }

    public function testRegisterRefusesEmailOfDeactivatedMemberAndSuggestsReactivation(): void
    {
        $client = $this->directorClient();

        $this->submitMemberForm($client, '/equipe/nouveau', ['firstName' => 'Arthur', 'lastName' => 'Petit', 'email' => 'ancien@example.com', 'role' => 'prod']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="team-member-form"]', 'réactivez-la');
    }

    public function testEditUpdatesMemberWithoutTouchingPassword(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();
        $newEmail = uniqid('renamed-', true) . '@example.com';

        $this->submitMemberForm($client, '/equipe/' . $member->getId() . '/modifier', ['lastName' => 'Renommé', 'email' => $newEmail, 'role' => 'lead']);

        self::assertResponseRedirects('/equipe');
        $member = $this->reloadUser($member);
        self::assertSame('Renommé', $member->getLastName());
        self::assertSame($newEmail, $member->getEmail());
        self::assertSame(Role::Lead, $member->getRole());
        self::assertTrue($this->passwordHasher()->isPasswordValid($member, 'password'));
    }

    public function testEditAttachesTheMemberToTheBelgianCalendar(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();

        $crawler = $client->request('GET', '/equipe/' . $member->getId() . '/modifier');
        self::assertSame('fr', $crawler->filter('[data-test^="member-holiday-calendar-"]:checked')->attr('value'));

        $this->submitMemberForm($client, '/equipe/' . $member->getId() . '/modifier', ['holidayCalendar' => 'be']);

        self::assertResponseRedirects('/equipe');
        self::assertSame(HolidayCalendar::Belgium, $this->reloadUser($member)->getHolidayCalendar());
    }

    public function testEditKeepingOwnEmailIsValid(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();

        $this->submitMemberForm($client, '/equipe/' . $member->getId() . '/modifier', ['firstName' => 'Prénom']);

        self::assertResponseRedirects('/equipe');
    }

    public function testDirectionSetsAWeeklyMaximumFromTheChosenWeek(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();
        $url = '/equipe/' . $member->getId() . '/modifier';

        $this->submitMemberForm($client, $url, ['weeklyMaxDays' => '4.5', 'weeklyMaxFrom' => '2026-10-07']);

        self::assertResponseRedirects('/equipe');
        $history = $this->weeklyMaxRepository()->findForUser($member);
        self::assertCount(1, $history);
        self::assertSame(18, $history[0]->getQuarters());
        self::assertSame('2026-10-05', $history[0]->getEffectiveFrom()->format('Y-m-d'));

        $client->request('GET', $url);
        self::assertSelectorTextContains('[data-test="weekly-max-entry"][data-from="2026-10-05"]', '4,5 j');
    }

    #[DataProvider('invalidWeeklyMaxProvider')]
    public function testInvalidWeeklyMaximumIsRefused(string $days): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();

        $this->submitMemberForm($client, '/equipe/' . $member->getId() . '/modifier', ['weeklyMaxDays' => $days]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->weeklyMaxRepository()->findForUser($member));
    }

    /**
     * @return \Generator<array{string}>
     */
    public static function invalidWeeklyMaxProvider(): \Generator
    {
        yield 'zero' => ['0'];
        yield 'more than five days' => ['5.5'];
        yield 'not a quarter of a day' => ['4.3'];
    }

    public function testDirectionDeletesAWeeklyMaximumValue(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();
        $this->createWeeklyMax($member, '2026-09-07', 16);

        $crawler = $client->request('GET', '/equipe/' . $member->getId() . '/modifier');
        $client->submit($crawler->filter('[data-test="weekly-max-entry"] [data-test="weekly-max-delete"]')->form());

        self::assertResponseRedirects('/equipe/' . $member->getId() . '/modifier');
        self::assertSame([], $this->weeklyMaxRepository()->findForUser($member));
    }

    public function testWeeklyMaximumValueOfAnotherMemberCannotBeDeletedThroughThisOne(): void
    {
        $client = $this->directorClient();
        $owner = $this->createUser();
        $weeklyMax = $this->createWeeklyMax($owner, '2026-09-07', 16);

        $client->request('POST', '/equipe/' . $this->createUser()->getId() . '/maximum/' . $weeklyMax->getId() . '/supprimer');

        self::assertResponseStatusCodeSame(404);
        self::assertCount(1, $this->weeklyMaxRepository()->findForUser($owner));
    }

    public function testDeactivateThenReactivateMember(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();

        $this->clickRowButton($client, $member, 'member-deactivate-confirm');
        self::assertResponseRedirects('/equipe');
        self::assertFalse($this->reloadUser($member)->isActive());

        $this->clickRowButton($client, $member, 'member-reactivate');
        self::assertResponseRedirects('/equipe');
        $member = $this->reloadUser($member);
        self::assertTrue($member->isActive());
        self::assertTrue($this->passwordHasher()->isPasswordValid($member, 'password'));
    }

    public function testLastActiveDirectorCannotDeactivateOrDemoteThemself(): void
    {
        $client = $this->directorClient();
        $director = $this->fixtureUser('admin@example.com');
        $otherDirectors = array_values(array_filter(
            $this->userRepository()->findBy(['role' => Role::Direction, 'active' => true]),
            static fn (User $user): bool => $user !== $director,
        ));
        $this->setActive($otherDirectors, false);

        try {
            $this->clickRowButton($client, $director, 'member-deactivate-confirm');
            self::assertResponseRedirects('/equipe');
            $client->followRedirect();
            self::assertSelectorTextContains('[role="alert"]', 'au moins un membre de la direction actif');

            $this->submitMemberForm($client, '/equipe/' . $director->getId() . '/modifier', ['role' => 'lead']);
            self::assertResponseRedirects('/equipe');

            $director = $this->reloadUser($director);
            self::assertTrue($director->isActive());
            self::assertSame(Role::Direction, $director->getRole());
        } finally {
            $this->setActive($otherDirectors, true);
        }
    }

    public function testResetPasswordInvalidatesPreviousPassword(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();

        $this->clickRowButton($client, $member, 'member-reset-password-confirm');

        self::assertResponseRedirects('/equipe/' . $member->getId() . '/mot-de-passe-provisoire');
        $temporaryPassword = $client->followRedirect()->filter('[data-test="temporary-password"]')->text();
        $member = $this->reloadUser($member);
        self::assertFalse($this->passwordHasher()->isPasswordValid($member, 'password'));
        self::assertTrue($this->passwordHasher()->isPasswordValid($member, $temporaryPassword));
        self::assertTrue($member->mustChangePassword());
    }

    public function testOpeningAnotherMemberTemporaryPasswordPageKeepsPendingOne(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();
        $other = $this->createUser();

        $this->clickRowButton($client, $member, 'member-reset-password-confirm');

        $client->request('GET', '/equipe/' . $other->getId() . '/mot-de-passe-provisoire');
        self::assertResponseRedirects('/equipe');

        $client->request('GET', '/equipe/' . $member->getId() . '/mot-de-passe-provisoire');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="temporary-password"]');
    }

    public function testStateChangeWithInvalidCsrfTokenIsRefused(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();

        $client->request('POST', '/equipe/' . $member->getId() . '/desactiver', ['_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->reloadUser($member)->isActive());
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
    private function submitMemberForm(KernelBrowser $client, string $url, array $values): void
    {
        $crawler = $client->request('GET', $url);
        $fields = [];
        foreach ($values as $field => $value) {
            $fields['team_member[' . $field . ']'] = $value;
        }

        $client->submit($crawler->filter('[data-test="team-member-form"]')->form($fields));
    }

    private function clickRowButton(KernelBrowser $client, User $member, string $button): void
    {
        $crawler = $client->request('GET', '/equipe');
        $client->submit($crawler->filter('[data-email="' . $member->getEmail() . '"] [data-test="' . $button . '"]')->form());
    }

    /**
     * @param list<User> $users
     */
    private function setActive(array $users, bool $active): void
    {
        $entityManager = $this->entityManager();
        foreach ($users as $user) {
            $entityManager->find(User::class, $user->getId())?->setActive($active);
        }
        $entityManager->flush();
    }

    private function fixtureUser(string $email): User
    {
        $user = $this->userRepository()->findOneByEmail($email);
        self::assertNotNull($user);

        return $user;
    }

    private function userRepository(): UserRepository
    {
        $userRepository = self::getContainer()->get(UserRepository::class);
        \assert($userRepository instanceof UserRepository);

        return $userRepository;
    }

    private function weeklyMaxRepository(): WeeklyMaxRepository
    {
        $repository = self::getContainer()->get(WeeklyMaxRepository::class);
        \assert($repository instanceof WeeklyMaxRepository);

        return $repository;
    }

    private function passwordHasher(): UserPasswordHasherInterface
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($passwordHasher instanceof UserPasswordHasherInterface);

        return $passwordHasher;
    }
}
