<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\Role;
use App\Enum\Type\TagCategory;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Repository\WeeklyMaxRepository;
use App\Tests\Support\CreatesTags;
use App\Tests\Support\CreatesTimeEntries;
use App\Tests\Support\CreatesUsers;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TeamControllerTest extends WebTestCase
{
    use CreatesTags;
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

    public function testListShowsIdentityRoleAndStatus(): void
    {
        $client = $this->directorClient();

        $client->request('GET', '/equipe');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-email="lead@example.com"] [data-test="member-role"]', 'Lead');
        self::assertSelectorTextContains('[data-email="ancien@example.com"] [data-test="member-status"]', 'Désactivée');
        self::assertSelectorNotExists('[data-email="prod@example.com"] [data-test="member-status"]');
    }

    public function testEachMemberLeadsToTheirPage(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();

        $crawler = $client->request('GET', '/equipe');

        $link = $crawler->filter(\sprintf('[data-email="%s"] [data-test="member-person"]', $member->getEmail()));
        self::assertSame('/personnes/' . $member->getId(), $link->attr('href'));
        self::assertSame('Fiche de Test User', $link->attr('aria-label'));
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

    public function testRegisterWithTagsReusesAnExistingTagTypedIgnoringCaseAndCreatesTheOthers(): void
    {
        $client = $this->directorClient();
        $existing = $this->createTag(TagCategory::TechnicalSkill);
        $experience = $this->createTag(TagCategory::FunctionalExperience);
        $newSkill = uniqid('Kubernetes ', true);
        $newTeamType = uniqid('Plateforme ', true);
        $email = uniqid('tags-', true) . '@example.com';

        $this->postMemberForm($client, '/equipe/nouveau', [
            'firstName' => 'Zoé',
            'lastName' => 'Tags',
            'email' => $email,
            'functionalExperiences' => [(string) $experience->getId()],
            'newTechnicalSkills' => mb_strtoupper($existing->getLabel()) . ', ' . $newSkill,
            'newTeamType' => $newTeamType,
        ]);
        self::assertResponseRedirects();

        $member = $this->userRepository()->findOneByEmail($email);
        self::assertNotNull($member);
        self::assertSame([$newSkill, $existing->getLabel()], self::labels($member->tagsOf(TagCategory::TechnicalSkill)), 'Kubernetes… sorts before Tag…');
        self::assertSame([$experience->getLabel()], self::labels($member->tagsOf(TagCategory::FunctionalExperience)));
        self::assertSame($newTeamType, $member->teamType()?->getLabel());
        self::assertSame(1, $this->countLabel(TagCategory::TechnicalSkill, $existing->getLabel()));
    }

    public function testAnInvalidMemberFormCreatesNoTag(): void
    {
        $client = $this->directorClient();
        $newSkill = uniqid('Elixir ', true);

        $this->postMemberForm($client, '/equipe/nouveau', ['firstName' => 'Zoé', 'lastName' => '', 'email' => uniqid('tags-', true) . '@example.com', 'newTechnicalSkills' => $newSkill]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->countLabel(TagCategory::TechnicalSkill, $newSkill));
    }

    public function testChoosingATeamTypeAndTypingANewOneIsRefused(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();
        $teamType = $this->createTag(TagCategory::TeamType);

        $this->postMemberForm($client, '/equipe/' . $member->getId() . '/modifier', ['teamType' => (string) $teamType->getId(), 'newTeamType' => uniqid('Front ', true)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="team-member-form"]', 'Choisissez un type d\'équipe existant ou saisissez-en un nouveau, pas les deux.');
        self::assertNull($this->reloadUser($member)->teamType());
    }

    public function testManagerChoicesOfferActivePeopleButNotThePersonEdited(): void
    {
        $client = $this->directorClient();
        $member = $this->createUser();
        $other = $this->createUser();
        $former = $this->createUser()->setActive(false);
        $this->entityManager()->flush();

        $crawler = $client->request('GET', '/equipe/' . $member->getId() . '/modifier');

        $choices = $crawler->filter('[data-test="member-manager"] option')->each(static fn (Crawler $option): string => (string) $option->attr('value'));
        self::assertContains((string) $other->getId(), $choices);
        self::assertNotContains((string) $member->getId(), $choices);
        self::assertNotContains((string) $former->getId(), $choices);
    }

    public function testChoosingAsManagerSomeoneThePersonManagesIsRefused(): void
    {
        $client = $this->directorClient();
        $manager = $this->createUser();
        $report = $this->createUser()->setManager($manager);
        $this->entityManager()->flush();

        $this->postMemberForm($client, '/equipe/' . $manager->getId() . '/modifier', ['manager' => (string) $report->getId()]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="team-member-form"]', 'formerait une boucle');

        $this->postMemberForm($client, '/equipe/' . $report->getId() . '/modifier', ['manager' => '']);
        self::assertResponseRedirects('/equipe');
        self::assertNull($this->reloadUser($report)->getManager());
    }

    public function testDeactivatingSomeoneWhoManagesActivePeopleIsRefusedNamingThem(): void
    {
        $client = $this->directorClient();
        $manager = $this->createUser();
        $report = $this->createUser()->setFirstName('Zoé')->setLastName(uniqid('Rattachée', false))->setManager($manager);
        $this->entityManager()->flush();

        $this->clickRowButton($client, $manager, 'member-deactivate-confirm');
        self::assertResponseRedirects('/equipe');
        $client->followRedirect();

        self::assertSelectorTextContains('[role="alert"]', 'Zoé ' . $report->getLastName());
        self::assertTrue($this->reloadUser($manager)->isActive());
    }

    public function testDeactivatingAManagerOfDeactivatedPeopleOnlyClearsTheirManager(): void
    {
        $client = $this->directorClient();
        $manager = $this->createUser();
        $former = $this->createUser()->setManager($manager)->setActive(false);
        $this->entityManager()->flush();

        $this->clickRowButton($client, $manager, 'member-deactivate-confirm');
        self::assertResponseRedirects('/equipe');

        self::assertFalse($this->reloadUser($manager)->isActive());
        self::assertNull($this->reloadUser($former)->getManager());
    }

    public function testListShowsTagsAndManagerAndFiltersByTagOfEachCategoryAndByDirectManager(): void
    {
        $client = $this->directorClient();
        $skill = $this->createTag(TagCategory::TechnicalSkill);
        $experience = $this->createTag(TagCategory::FunctionalExperience);
        $teamType = $this->createTag(TagCategory::TeamType);
        $manager = $this->createUser()->setFirstName('Zoé')->setLastName(uniqid('Manager', false));
        $both = $this->giveTags($this->createUser()->setManager($manager), $skill, $experience, $teamType);
        $skillOnly = $this->giveTags($this->createUser(), $skill);
        $none = $this->createUser();

        $crawler = $client->request('GET', '/equipe');
        $row = $crawler->filter(\sprintf('[data-email="%s"]', $both->getEmail()));
        self::assertSame($teamType->getLabel(), $row->filter('[data-test="member-team-type"]')->text());
        self::assertSame([$skill->getLabel(), $experience->getLabel()], $row->filter('[data-test="member-tags"] [data-test="member-tag"]')->each(static fn (Crawler $tag): string => $tag->text()));
        self::assertSame('Zoé ' . $manager->getLastName(), $row->filter('[data-test="member-manager"]')->text());

        self::assertSame([$both->getEmail(), $skillOnly->getEmail()], $this->listedAmong($client, ['competence' => $skill->getId()], [$both, $skillOnly, $none]));
        self::assertSame([$both->getEmail()], $this->listedAmong($client, ['competence' => $skill->getId(), 'experience' => $experience->getId()], [$both, $skillOnly, $none]));
        self::assertSame([$both->getEmail()], $this->listedAmong($client, ['manager' => $manager->getId()], [$both, $skillOnly, $none]));

        $crawler = $client->request('GET', '/equipe?' . http_build_query(['competence' => $skill->getId()]));
        self::assertCount(3, $crawler->filter(\sprintf('[data-email="%s"] [data-test="member-tag"]', $both->getEmail())), 'A filtered person keeps all their tags.');
    }

    public function testTheListQueryCountDoesNotGrowWithThePeopleListed(): void
    {
        $client = $this->directorClient();
        $small = $this->queryCount($client, '/equipe');

        $manager = $this->createUser();
        for ($i = 0; $i < 3; ++$i) {
            $this->giveTags($this->createUser()->setManager($manager), $this->createTag(), $this->createTag(TagCategory::TeamType));
        }

        self::assertSame($small, $this->queryCount($client, '/equipe'));
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

    /**
     * Posts the member form as the page renders it, with some fields replaced: unlike a crawler form, it can tick the
     * checkboxes of tags created by the test.
     *
     * @param array<string, string|list<string>> $values
     */
    private function postMemberForm(KernelBrowser $client, string $url, array $values): void
    {
        $form = $client->request('GET', $url)->filter('[data-test="team-member-form"]')->form();
        $data = $form->getPhpValues();
        \assert(\is_array($data['team_member']));
        $data['team_member'] = array_replace($data['team_member'], $values);

        $client->request('POST', $form->getUri(), $data);
    }

    /**
     * @param array<string, int|null> $filter
     * @param list<User>              $among
     *
     * @return list<string> the e-mails of the people among these that the filtered list shows
     */
    private function listedAmong(KernelBrowser $client, array $filter, array $among): array
    {
        $crawler = $client->request('GET', '/equipe?' . http_build_query($filter));
        self::assertResponseIsSuccessful();
        $listed = $crawler->filter('[data-test="team-member-row"]')->each(static fn (Crawler $row): string => (string) $row->attr('data-email'));

        return array_values(array_filter(array_map(static fn (User $user): string => (string) $user->getEmail(), $among), static fn (string $email): bool => \in_array($email, $listed, true)));
    }

    /**
     * @param list<Tag> $tags
     *
     * @return list<string>
     */
    private static function labels(array $tags): array
    {
        return array_map(static fn (Tag $tag): string => $tag->getLabel(), $tags);
    }

    private function countLabel(TagCategory $category, string $label): int
    {
        $repository = self::getContainer()->get(TagRepository::class);
        \assert($repository instanceof TagRepository);
        $labels = array_map(mb_strtolower(...), $repository->findLabelsExcept($category, null));

        return \count(array_keys($labels, mb_strtolower($label), true));
    }

    private function queryCount(KernelBrowser $client, string $url): int
    {
        $client->enableProfiler();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();

        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
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
